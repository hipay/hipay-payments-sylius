# Troubleshooting

Common problems encountered when installing or running the plugin, with their root cause and fix.

---

## Table of contents

- [Webhooks](#webhooks)
- [Checkout / Hosted Fields](#checkout--hosted-fields)
- [Apple Pay](#apple-pay)
- [Payment state / transitions](#payment-state--transitions)
- [Refunds](#refunds)
- [PHP configuration](#php-configuration)
- [Logs and debugging](#logs-and-debugging)

---

## Webhooks

### `Invalid signature` — webhook rejected with HTTP 403

**Symptom:** `var/log/hipay.log` contains `[Hipay][RequestParser] Invalid signature.` HiPay marks the notification as failed.

**Causes and fixes:**

| Cause | Fix |
|-------|-----|
| Wrong `secret_passphrase` saved on the HiPay Account in Sylius admin | Check **Configuration → HiPay → Accounts** — the passphrase must match exactly what is set in the HiPay back-office for the same environment (stage vs production). |
| Environment mismatch (stage passphrase used in production or vice versa) | The plugin calls `getSecretPassphraseForCurrentEnv()` — ensure both passphrases are set on the Account and that `APP_ENV` is correct. |
| Trailing whitespace or newline copied into the passphrase field | Re-enter the passphrase manually; avoid copy-paste from a text editor that may add invisible characters. |
| Notification URL not reachable (HiPay cannot POST to your server) | Verify the `notify_url` route (`sylius_hipay_plugin_webhook`) is publicly accessible. In local dev, use a tunnel (ngrok, Expose, etc.). |

---

### `No account found for this payment` — webhook rejected

**Symptom:** `var/log/hipay.log` contains `No account found for this payment` with a `transaction_reference`. HTTP 403 returned to HiPay.

**Cause:** The `hipay_transaction` row for this `transaction_reference` does not exist or is not linked to a known Account.

**Fix:**
1. Check that the initial `requestNewOrder` call succeeded and that a `hipay_transaction` row was persisted (see `var/log/hipay.log` for `[Hipay][NewOrderRequestHandler] After sending request`).
2. If the transaction row is missing, the synchronous API call likely failed silently — inspect the log for errors around the same `request_id`.

---

### Webhook received but no Sylius payment transition applied

**Symptom:** HiPay confirms the notification was delivered (HTTP 200), but the Sylius payment state does not change.

**Causes:**

| Cause | Fix |
|-------|-----|
| Status code not in `HiPayStatus` enum | The plugin ignores unknown codes. Check [hipay-status-mapping.md](./hipay-status-mapping.md) — if the code is new, add it to [`src/Payment/HiPayStatus.php`](../src/Payment/HiPayStatus.php). |
| Transition already applied (payment already in target state) | Sylius state machine rejects duplicate transitions — this is correct behaviour. Check the payment state in the admin. |
| `BeforeWebhookNotificationProcessedEvent` listener throwing an exception | Check `var/log/hipay.log` and `var/log/prod.log` for exceptions in your own listeners. |

---

## Checkout / Hosted Fields

### Hosted Fields form does not appear

**Symptom:** The payment form area is blank or the loader spins indefinitely.

**Causes and fixes:**

| Cause | Fix |
|-------|-----|
| Content Security Policy blocking the HiPay JS SDK | Check browser console for CSP errors. Add the required directives from [content-security-policy.md](./content-security-policy.md). |
| SRI hash mismatch | `hipay_integrity_hash()` fetches the hash from HiPay CDN at runtime. If the CDN is unreachable at Symfony cache warmup time, the hash may be stale or empty. Clear cache and try again. |
| `payment_product` not set or invalid in gateway config | The `hipay-hosted-fields` Stimulus controller will silently fail if `initialConfig.product` is missing. Check the gateway configuration in Sylius admin — `payment_product` must match a supported HiPay product code. |
| Stimulus controller not compiled / not registered | Ensure `@hipay/sylius-hipay-plugin` entries are present in `assets/controllers.json` and that your front-end build includes the controller. Run `yarn encore dev` (or `importmap:install`) and hard-refresh. |
| LiveComponent not mounted (JS error: "Live component not found") | The Stimulus controller polls for `element.__component` up to 50 times. If the LiveComponent fails to boot (PHP error during hydration), the form will stay blank. Check `var/log/prod.log` for PHP exceptions. |

---

### "Pay" button stays disabled after filling card fields

**Symptom:** Card fields are filled but the pay button remains disabled.

**Cause:** The HiPay SDK fires a `change` event with `valid: false` — typically a field validation error.

**Fix:** Open the browser console and check for HiPay SDK warnings. Common reasons: unsupported card brand for the configured `brand` filter, or a required field (CVV, expiry) not yet filled.

---

### `processPayment` LiveAction does nothing after form submit

**Symptom:** `hipay:payment:processed` fires on `window` but the checkout form is not submitted, or the page reloads without advancing.

**Cause:** The `closest('form')` lookup in `submitForm()` failed because the Stimulus controller element is not inside the checkout `<form>`.

**Fix:** Verify the DOM structure — the `hipay-hosted-fields` controller element must be a descendant of `<form name="sylius_shop_checkout_select_payment">`.

---

## Apple Pay

### Apple Pay button not displayed

**Symptom:** The Apple Pay payment option is not shown to the customer, or `checkBrowserSupport` returns `false`.

**Causes:**

| Cause | Fix |
|-------|-----|
| Browser / device does not support Apple Pay | Apple Pay requires Safari on macOS/iOS with a card added to Wallet. Not available on Chrome/Firefox. |
| Domain not validated with Apple | You must host the Apple Pay domain verification file at `/.well-known/apple-developer-merchantid-domain-association`. Refer to the functional documentation and HiPay back-office for the file content. |
| Not on HTTPS | `ApplePaySession.canMakePayments()` always returns `false` on HTTP. |

---

## Payment state / transitions

### Payment stuck in `processing` state after checkout

**Symptom:** Customer completed checkout but payment stays `processing`. No notification received.

**Causes and fixes:**

| Cause | Fix |
|-------|-----|
| HiPay notification URL unreachable | See [Webhooks — Invalid signature](#webhooks) section above. Check that `notify_url` is a public HTTPS URL. |
| 3DS forwarding URL not followed | If HiPay returned a `forwardUrl` (3DS), the customer must complete 3DS in the browser. The payment stays pending until HiPay sends the final notification. |
| Sylius Messenger worker not running | Payment request transitions are processed synchronously, but if you have async routing configured, ensure `bin/console messenger:consume` is running. |

---

### Payment in `on_hold` state (fraud sentinel)

**Symptom:** Payment is set to `on_hold` and a fraud suspicion email was sent.

**Cause:** HiPay returned a `pending` transaction state synchronously — the order is under fraud review (status 112 `AuthorizedAndPending`).

**Fix:** Wait for HiPay's final notification. When HiPay resolves the review, it sends a notification (e.g. status 116 `Authorized` or 110 `Blocked`) and `NotificationProcessor` will unhold and apply the correct transition automatically.

---

## Refunds

### Refund fails silently — `hipay_transaction` not found

**Symptom:** Refund is created in Sylius but `var/log/hipay.log` shows `HiPay transaction reference not found for this payment`.

**Cause:** The `hipay_transaction` row was never persisted — the original `requestNewOrder` call failed before `saveTransaction()` was reached.

**Fix:** Check `var/log/hipay.log` for errors during the original capture. The `transaction_reference` must exist in `hipay_transaction` before a refund can be issued through the plugin.

---

### Refund succeeds on HiPay but Sylius payment state does not update

**Symptom:** HiPay dashboard shows the refund, but Sylius payment state stays `completed`.

**Cause:** Refund state transitions in Sylius are driven by webhook notifications (status 124 / 125 / 126), not by the `RefundPaymentGeneratedHandler` response.

**Fix:** Ensure webhooks are reachable and that the `refund` transition is allowed by your Sylius payment workflow. See [hipay-status-mapping.md](./hipay-status-mapping.md) for refund status codes.

---

## PHP configuration

### `serialize_precision`: use `-1`, not `17`

**Symptom:** Amounts in JSON (logs, API payloads, `json_encode`) look wrong — e.g. `6.22` becomes `6.2199999999999998`.

```bash
# With serialize_precision = 17 (problematic for display / JSON)
php -d serialize_precision=17 -r "echo json_encode(['amount' => 6.22]);"
# {"amount":6.2199999999999998}
```

**Cause:** When `serialize_precision` is set to `17` (a common legacy or copied value), PHP serializes `float` values with enough decimal digits to represent the full IEEE-754 *binary* double. That is correct for round-trip fidelity in some edge cases, but the extra digits expose the fact that many *decimal* literals are not representable exactly in binary — so `json_encode` prints the “long” form of the number.

**Fix:** Set `serialize_precision` to **`-1`** in `php.ini` (or your platform/PHP pool config). With `-1`, PHP uses an algorithm that picks a **shorter** decimal string that still round-trips to the same float — which is what you usually want for JSON and human-readable output.

```bash
# With serialize_precision = -1 (recommended)
php -d serialize_precision=-1 -r "echo json_encode(['amount' => 6.22]);"
# {"amount":6.22}
```

Copy `php.ini.dist` to `php.ini` for local use (see `Makefile`) and add `serialize_precision = -1` if your environment does not already set it. The deployment template uses `-1` (e.g. `.upsun/config.yaml`).

**Note:** For *money* amounts, the robust approach is to avoid binary floats in business logic (e.g. store minor units as integers, use a money library) — this setting only fixes how existing floats are **serialized** to strings/JSON, not the underlying type.

---

## Logs and debugging

### Enable verbose logging for a specific account

Set `debug_mode = true` on the HiPay Account in **Configuration → HiPay → Accounts**. The Stimulus controller will also output grouped console logs in the browser for that account's checkout.

### Read payment logs

```bash
# Follow live
tail -f var/log/hipay.log

# Search for a specific payment
grep "payment_id\":22" var/log/hipay.log

# Search for a specific transaction reference
grep "800423847267" var/log/hipay.log
```

### Correlate a full API round-trip

Each `NewOrderRequestHandler` call generates a UUID `request_id`. Use it to find both the "Before sending request" and "After sending request" lines:

```bash
grep "019d6de7-dc4b-7bce-9b53-dd92c3290c3c" var/log/hipay.log
```

### Test webhook signature locally

To replay a real HiPay notification against your local environment:

```bash
curl -X POST https://your-tunnel-url/payment/hipay/notify \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "transaction_reference=800423847267" \
  --data-urlencode "status=118" \
  --data-urlencode "state=completed" \
  # ... other fields from a real HiPay notification
```

Note: the HMAC signature check will reject requests without a valid `X-Allopass-Signature` header — use a real captured request from HiPay logs or temporarily disable HMAC in a local test environment.
