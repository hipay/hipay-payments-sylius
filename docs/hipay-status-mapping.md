# HiPay status codes → Sylius mapping

HiPay sends a numeric **`status`** in [server-to-server notifications](https://developer.hipay.com/payment-fundamentals/requirements/notifications). This plugin maps those codes in [`src/Payment/HiPayStatus.php`](../src/Payment/HiPayStatus.php).

**Official reference**

- [Transaction status](https://developer.hipay.com/payment-fundamentals/essentials/transaction-status)
- [Notifications](https://developer.hipay.com/payment-fundamentals/requirements/notifications)

## Table of contents

- [Sylius transition and PaymentRequest action semantics](#sylius-transition-and-paymentrequest-action-semantics)
- [Exhaustive mapping (plugin enum)](#exhaustive-mapping-plugin-enum)
- [Special cases](#special-cases)
- [LLM note](#llm-note)

---

## Sylius transition and PaymentRequest action semantics

| Column | Meaning |
|--------|---------|
| **Sylius transition** | Applied to **`Payment`** via Sylius `PaymentTransitions` / plugin `PaymentTransitions` when allowed by the state machine (`HiPayStatus::toSyliusTransition()`). `—` = no transition. |
| **PaymentRequest action** | Used when creating the webhook-side **`PaymentRequest`** (`HiPayStatus::toPaymentRequestAction()`). Aligns with `Sylius\Component\Payment\Model\PaymentRequestInterface` constants. |

Sylius transition constant names in code: `process`, `authorize`, `complete`, `fail`, `refund`, `cancel`, `void`, and plugin `hold` (`HiPay\SyliusHiPayPlugin\Payment\PaymentTransitions::HOLD`).

---

## Exhaustive mapping (plugin enum)

| Code | HiPayStatus (enum case) | Sylius payment transition | PaymentRequest action |
|------|-------------------------|---------------------------|------------------------|
| 109 | `AuthenticationFailed` | `fail` | `cancel` |
| 110 | `Blocked` | `fail` | `cancel` |
| 111 | `Denied` | `fail` | `cancel` |
| 113 | `Refused` | `fail` | `cancel` |
| 114 | `Expired` | `cancel` | `cancel` |
| 115 | `Cancelled` | `cancel` | `cancel` |
| 173 | `CaptureRefused` | `fail` | `cancel` |
| 178 | `SoftDeclined` | `fail` | `cancel` |
| 112 | `AuthorizedAndPending` | `hold` | `status` |
| 116 | `Authorized` | `authorize` | `authorize` |
| 117 | `CaptureRequested` | — | `capture` |
| 118 | `Captured` | `complete` | `capture` |
| 119 | `PartiallyCaptured` | `complete` | `capture` |
| 142 | `AuthorizationRequested` | `process` | `status` |
| 144 | `ReferenceRendered` | `process` | `status` |
| 172 | `InProgress` | `process` | `status` |
| 174 | `AwaitingTerminal` | `process` | `status` |
| 200 | `PendingPayment` | `process` | `status` |
| 124 | `RefundRequested` | `refund` | `refund` |
| 125 | `Refunded` | `refund` | `refund` |
| 126 | `PartiallyRefunded` | `refund` | `refund` |
| 165 | `RefundRefused` | `fail` | `cancel` |
| 166 | `CardholderCredit` | `refund` | `refund` |
| 168 | `DebitedCardholderCredit` | — | `status` |
| 169 | `CreditRequested` | `refund` | `refund` |
| 182 | `PartiallyRefundByRdr` | `refund` | `refund` |
| 183 | `RefundByRdr` | `refund` | `refund` |
| 129 | `Unpaid` | `refund` | `refund` |
| 134 | `DisputeLost` | `refund` | `refund` |
| 180 | `PartiallyChargeback` | `refund` | `refund` |
| 181 | `Chargeback` | `refund` | `refund` |
| 143 | `AuthorizationCancelled` | `void` | `cancel` |
| 175 | `AuthorizationCancellationRequested` | — | `status` |

---

## Special cases

### 3DS / forwarding (synchronous response)

**Webhook table above** does not list “forwarding” — that comes from the **HiPay SDK transaction** after `requestNewOrder`, stored on **`PaymentRequest::responseData`**. [`HostedFieldsHttpResponseProvider`](../src/OrderPay/Provider/HostedFieldsHttpResponseProvider.php) redirects to **`forwardUrl`** when state is **forwarding**. This is orthogonal to notification `status` codes.

### On hold / fraud sentinel

When the synchronous API returns a **pending** transaction state, [`NewOrderRequestHandler`](../src/CommandHandler/NewOrderRequestHandler.php) may set the Sylius payment **on hold** and send a fraud-suspicion email. Later **notifications** with a final status drive **`NotificationProcessor`**, which can **unhold** and apply the mapped transition.

### Chargebacks and disputes

`PartiallyChargeback`, `Chargeback`, `DisputeLost`, `Unpaid`, and related rows are mapped to Sylius **`refund`** transition and **`refund`** PaymentRequest action in this plugin (see enum docblock in code). Adjust business rules via Sylius workflow permissions or custom subscribers if you need a different accounting model.

### Unknown status codes

If HiPay sends a code **not** in the enum, `HiPayStatus::tryFrom` yields `null`; helpers `getSyliusTransition` / `getPaymentRequestAction` return `null` — **no automatic transition**. Extend **`HiPayStatus`** when HiPay documents new codes.

---

## LLM note

When debugging webhooks, always cross-check **raw POST `status`** with this table and with [`NotificationProcessor`](../src/Webhook/NotificationProcessor.php). For checkout issues, inspect **`Payment::details`** and synchronous **`responseData`**, not only notification status.
