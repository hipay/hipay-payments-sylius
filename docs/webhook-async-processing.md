# Webhook asynchronous processing (SyliusHiPayPlugin)

HiPay notifications arrive at `POST /payment/hipay/notify` and are processed **asynchronously** through a Symfony Scheduler worker. The HTTP layer only validates the HMAC signature and payload shape, then inserts a row into `hipay_pending_notification` and returns `HTTP 202 No Content` immediately. Business processing (state machine transitions, `PaymentRequest` creation, event subscribers, orphan-payment cleanup) runs in a background worker that claims eligible rows in priority order.

This document covers the architecture, configuration, operations (local + Upsun), failure handling, and tuning parameters.

**Related:** [payment-workflow.md](./payment-workflow.md) • [events.md](./events.md) • [logging.md](./logging.md) • [troubleshooting.md](./troubleshooting.md)

---

## Why asynchronous?

Two problems drove the move off synchronous processing:

1. **Race with the checkout API.** HiPay sometimes delivers the notification before the Sylius checkout API has finished persisting the payment. A synchronous handler would throw `No payment found`, HiPay would receive an HTTP 5xx, and retry aggressively with no controlled backoff.
2. **HTTP latency.** The synchronous path locked the order, transitioned the state machine, dispatched subscribers (saved cards, fraud alerts, refund-plugin integration), and persisted. All of it blocked the HTTP response while HiPay expects a fast acknowledgment.

Buffering with a priority queue gives us:

- a fast, deterministic webhook response (single `INSERT`);
- a configurable **buffer window** to absorb the checkout race;
- per-transaction ordering driven by status priority groups;
- bounded, exponential retry with per-row error isolation.

---

## Architecture

```
HiPay POST  ─►  webhook.controller::handle
               │
               ├─►  RequestParser::doParse                    [SYNC, HTTP thread]
               │     • Method / Content-Type / HMAC / mandatory fields
               │     • Rejected → RejectWebhookException → 400 / 403 / 406
               │
               └─►  ConsumeRemoteEventHandler → Consumer::consume(RemoteEvent)
                     • INSERT into hipay_pending_notification
                     •   state       = PENDING
                     •   priority    = HiPayStatus::toNotificationPriority()
                     •   available_at = NOW() + buffer_seconds
                     • Duplicate event_id? UniqueConstraintViolation → silent no-op
                     │
                     └─►  HTTP 202 No Content        (single INSERT, ≪ 100 ms)


                                        [every `interval_seconds`, in a worker]

  messenger:consume scheduler_hipay_notifications
     │
     └─►  HiPayNotificationsSchedule → ProcessPendingBatchMessage
           │
           └─►  ProcessPendingBatchHandler::__invoke
                 │
                 ├─►  PendingNotificationRepository::claimBatch()
                 │     • SELECT … WHERE (state=PENDING AND available_at <= now)
                 │     •                 OR (state=PROCESSING AND claimed_at <= stale_cutoff)
                 │     • ORDER BY priority ASC, id ASC
                 │     • LIMIT batch_size FOR UPDATE SKIP LOCKED
                 │     • UPDATE … SET state=PROCESSING, claimed_at=NOW()
                 │
                 └─►  For each row:
                       NotificationProcessor::process(eventId, payload)  [UNCHANGED]
                         • OrderAdvisoryLock
                         • state machine transition (idempotent)
                         • BeforeWebhookNotificationProcessed + After… events
                         • orphan payment cleanup
                       │
                       ├─  success            → state=COMPLETED, processed_at=NOW()
                       ├─  UnrecoverableEx    → state=FAILED (no retry)
                       └─  Throwable
                             ├─ attempts < max → PENDING + exponential `available_at`
                             └─ attempts ≥ max → state=FAILED
```

### Priority order

`HiPayStatus::toNotificationPriority()` assigns each status to one of twelve groups (lower = earlier). Grouping ensures that — for a given transaction — state-machine transitions are applied in a deterministic order regardless of the HiPay callback arrival order:

| Priority | HiPay statuses |
|---|---|
| 1 | In progress (authorization requested, pending, reference rendered, awaiting terminal, credit requested, pending payment) |
| 2 | Failure (authentication failed, blocked, denied, refused, expired, dispute lost, soft declined) |
| 3 | Chargeback / partial chargeback |
| 4 | Authorized |
| 5 | Capture requested / capture refused |
| 6 | Partially captured |
| 7 | Paid (captured, cardholder credit debited) |
| 8 | Refund requested / refund refused |
| 9 | Partially refunded |
| 10 | Refunded |
| 11 | Cancellations (customer cancel, authorization cancelled, cancellation requested) |
| 12 | Everything else (informational: enrolment / authentication / settlement statuses) |

Unknown statuses fall back to priority **99** so they never delay a state-changing notification for the same transaction.

### Deduplication

HiPay re-sends the same notification on network hiccups. `event_id` (mapped from `attempt_id`) carries a **UNIQUE** constraint on `hipay_pending_notification`. The second `INSERT` raises `UniqueConstraintViolationException`, which the Consumer catches and logs as a no-op — the first row is already queued or being processed.

### Ordering and concurrency

`claimBatch()` uses `SELECT … FOR UPDATE SKIP LOCKED`, so it is safe to run **N** workers in parallel: each worker grabs a disjoint batch. With a single worker, the strict `ORDER BY priority ASC, id ASC` produces a globally deterministic sequence. With N workers, per-order ordering still holds thanks to [`OrderAdvisoryLock`](../src/Payment/OrderAdvisoryLock.php), which serialises the state-machine transition per order across workers.

A notification whose transition is not applicable (e.g. `Captured` arriving before `Authorized`) returns through `StateMachine::can() === false` and is marked COMPLETED (idempotent no-op). The earlier notification retries through the backoff and eventually lands the transition.

Requires **MySQL 8.0+** for `SKIP LOCKED`.

---

## Configuration parameters

All parameters are declared with sensible defaults in [config/services.php](../config/services.php). The plugin works out of the box with zero configuration; override any value in your application's `config/services.yaml` (or `config/packages/sylius_hipay_plugin.yaml`) to tune it:

```yaml
# config/services.yaml
parameters:
    # How long Consumer delays a notification before the worker can claim it.
    # Absorbs the race between HiPay's callback and the checkout finalisation.
    sylius_hipay_plugin.webhook.buffer_seconds: 180

    # Schedule tick cadence — the worker wakes up every N seconds to look for
    # eligible rows.
    sylius_hipay_plugin.webhook.scheduler.interval_seconds: 30

    # Max rows claimed per tick.
    sylius_hipay_plugin.webhook.scheduler.batch_size: 50

    # Hard cap: after N failed attempts, the row is marked FAILED.
    sylius_hipay_plugin.webhook.scheduler.max_attempts: 8

    # Exponential backoff envelope for transient errors (delay = base * 2^(attempts-1), capped at max).
    sylius_hipay_plugin.webhook.scheduler.retry_base_delay_seconds: 30
    sylius_hipay_plugin.webhook.scheduler.retry_max_delay_seconds: 3600

    # Grace window after which a PROCESSING row whose worker died can be reclaimed.
    sylius_hipay_plugin.webhook.scheduler.stalled_claim_timeout_seconds: 600
```

---

## Running the worker

### Production (systemd / Supervisor / Upsun / Docker)

Run one process per environment (horizontally scalable to N — `SKIP LOCKED` handles contention):

```bash
php bin/console messenger:consume scheduler_hipay_notifications \
    --time-limit=3600 \
    --memory-limit=128M \
    -v
```

- `--time-limit` recycles the worker every hour (SIGTERM on boundary, then the process manager restarts it). This keeps Doctrine's identity map and long-lived HTTP clients fresh.
- `--memory-limit` is a defensive ceiling (the worker itself should not leak, but a leaky subscriber shouldn't ruin the day).

#### Upsun

`.upsun/config.yaml` already declares the worker:

```yaml
workers:
    hipay_scheduler:
        commands:
            start: symfony console messenger:consume scheduler_hipay_notifications --time-limit=3600 --memory-limit=128M -v
```

Scale with `symfony cloud:worker:scale hipay_scheduler:2` if your volume warrants it.

### Local development

`.symfony.local.yaml` runs the worker together with `symfony server:start`:

```yaml
workers:
    hipay_scheduler:
        cmd:   ['symfony', 'console', 'messenger:consume', 'scheduler_hipay_notifications', '--time-limit=3600', '--memory-limit=128M', '-vv']
        watch: ['src', 'config']
```

Stream the worker logs alongside the web server:

```bash
symfony server:log --filter=workers
```

---

## Monitoring and failure recovery

### Inspect the queue

```sql
-- Pending backlog (oldest first)
SELECT id, event_id, status, priority, state, attempts, available_at, last_error
FROM   hipay_pending_notification
WHERE  state IN ('pending', 'processing')
ORDER  BY priority ASC, id ASC
LIMIT  100;

-- Recent failures
SELECT id, event_id, transaction_reference, status, attempts, last_error, processed_at
FROM   hipay_pending_notification
WHERE  state = 'failed'
ORDER  BY processed_at DESC
LIMIT  50;
```

Logs land in the dedicated `hipay` channel (`var/log/hipay.log` by default — see [logging.md](./logging.md)):

- `[Hipay][Consumer] Notification buffered` — INSERT landed.
- `[Hipay][Consumer] Duplicate notification ignored` — UNIQUE collision on `event_id`.
- `[Hipay][Scheduler] Batch claimed` / `Notification processed` — happy path.
- `[Hipay][Scheduler] Notification deferred for retry` — transient error; `available_at` pushed forward.
- `[Hipay][Scheduler] Notification permanently failed` — `FAILED` state.

### Replay a failed row

Flip the row back to `pending` and clear its claim; the worker will pick it up on the next tick:

```sql
UPDATE hipay_pending_notification
SET    state = 'pending',
       attempts = 0,
       available_at = NOW(),
       claimed_at = NULL,
       last_error = NULL
WHERE  id = :id;
```

For an outage-level replay, filter on `last_error LIKE '%pattern%'` or on a time range.

### Stalled workers

If a worker is killed while processing a row, that row stays in `state = 'processing'` until `stalled_claim_timeout_seconds` elapses — the next tick then reclaims it automatically via the `(state=processing AND claimed_at <= stale_cutoff)` branch of `claimBatch`. No manual intervention required unless `max_attempts` is already exhausted.

### Observability gap vs. HiPay dashboard

Previously, a synchronous failure bubbled up as HTTP 5xx on the HiPay dashboard. With async processing, failures are invisible to HiPay. Compensate by monitoring the `state = 'failed'` row count (SQL cron, Grafana, alerting) — this is the single point of truth for unhealthy notifications.

---

## Tuning guidance

| Symptom | Lever |
|---|---|
| High checkout-vs-callback race rate (many retries on first attempts) | Increase `buffer_seconds` (e.g. 300s). |
| Queue drains too slowly under spikes | Increase `batch_size`, or scale the worker horizontally. |
| Worker CPU idle most of the time | Increase `interval_seconds` (e.g. 60s) — trades tick latency for DB-poll cost. |
| Transient errors exhaust `max_attempts` too quickly | Increase `max_attempts` or `retry_max_delay_seconds`. |
| Dead workers keep rows pinned too long | Lower `stalled_claim_timeout_seconds` — but keep it well above `NotificationProcessor`'s worst-case runtime. |

---

## Contract of the webhook endpoint

| Case | Response | Side effect |
|---|---|---|
| Valid HMAC + payload | **202** | Row inserted into `hipay_pending_notification`. |
| Duplicate `event_id` | **202** | No row inserted; silent no-op. |
| Invalid HMAC | **403** | Nothing persisted. |
| Missing mandatory field / bad Content-Type | **400** / **406** | Nothing persisted. |
| DB error during INSERT | **5xx** | HiPay retries — acceptable (rare, infrastructural). |

A 202 response **does not** mean the notification has been applied — only that it has been durably queued. Observe the row's `state` transition to `completed` for end-to-end confirmation.
