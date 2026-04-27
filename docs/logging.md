# Logging and PII redaction

The plugin writes all payment-related logs to a **dedicated `hipay` channel**, separate from your application's main log. Sensitive data (card numbers, customer PII, authentication tokens) is automatically redacted before writing.

---

## Log channel and file

Configured in [`config/monolog.yaml`](../config/monolog.yaml):

```yaml
monolog:
    channels: ['hipay']
    handlers:
        hipay:
            type: stream
            path: "%kernel.logs_dir%/hipay.log"
            level: debug
            channels: ['hipay']
```

In production this writes to `var/log/hipay.log`. To redirect to a different destination (syslog, Graylog, etc.), override this handler in your application's `config/packages/prod/monolog.yaml`.

---

## What is logged

| Event | Level | Context keys |
|-------|-------|-------------|
| Before HiPay API request | `INFO` | `request_id`, `action`, `order_request` (redacted) |
| After HiPay API response | `INFO` | `request_id`, `action`, `transaction` (redacted) |
| Webhook notification received | `INFO` | `event_id`, `transition`, `status`, `payment_id` |
| Refund successful | `INFO` | `payment_id`, `transaction` |
| Missing order / payment / account | `WARNING` | `orderNumber` or `payment_id` |
| API or refund failure | `ERROR` | `payment_id`, `transaction`, exception message |

---

## PII redaction

[`HiPayLogger`](../src/Logging/HiPayLogger.php) wraps every log call through [`RedactSensitiveProcessor`](https://github.com/leocavalcante/redact-sensitive) before writing. Fields are fully masked (`***`) in all output.

**Redacted categories:**

| Category | Examples |
|----------|---------|
| Card data | `pan`, `cardHolder`, `cardtoken`, `cvc`, `cvv`, expiry dates |
| Customer billing | `firstname`, `lastname`, `email`, `birthdate`, `phone`, `streetaddress`, `city`, `zipcode` |
| Customer shipping | `shipto_firstname`, `shipto_lastname`, `shipto_streetaddress`, `shipto_city`, etc. |
| Authentication | `token`, `authenticationToken`, `authorizationCode`, `authentication_indicator` |
| Network | `ipaddr`, `device_fingerprint` |

The full list of redacted keys is defined as constants in [`HiPayLogger::SENSITIVE_KEYS`](../src/Logging/HiPayLogger.php).

---

## Debug mode per Account

Each HiPay **Account** has a `debug_mode` flag (configurable in the Sylius admin under **Configuration → HiPay → Accounts**).

When `debug_mode` is enabled for an account:
- Logs are routed to the **inner logger** (a second, more verbose handler you can configure).
- The Stimulus controller also outputs debug groups to the browser console.

This allows enabling verbose logging for a single account (e.g. staging credentials) without affecting production accounts.

**How to configure a separate debug handler** in your application:

```yaml
# config/packages/dev/monolog.yaml
monolog:
    handlers:
        hipay_debug:
            type: stream
            path: "%kernel.logs_dir%/hipay_debug.log"
            level: debug
            channels: ['hipay']
```

Then inject this handler as the `innerLogger` in your service override if needed.

---

## Reading logs in production

```bash
# Upsun / Platform.sh
upsun ssh -e <environment> -- tail -f var/log/hipay.log

# Local
tail -f var/log/hipay.log
```

When debugging a webhook, cross-reference the `request_id` (UUID) across the "Before sending request" and "After sending request" log lines to trace the full API round-trip.
