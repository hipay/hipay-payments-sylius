# Sylius Refund Plugin integration

This plugin has built-in support for [`sylius/refund-plugin`](https://github.com/Sylius/RefundPlugin). When the Refund Plugin is installed, HiPay refunds are processed automatically through the HiPay API — no extra configuration required.

**Related docs**

- [Payment workflow](./payment-workflow.md)
- [HiPay status mapping](./hipay-status-mapping.md)

---

## How it works

### Automatic gateway registration

[`AddRefundGatewayPass`](../src/RefundPlugin/Compiler/AddRefundGatewayPass.php) is a Symfony compiler pass registered in [`SyliusHiPayPlugin`](../src/SyliusHiPayPlugin.php). At container build time it adds `hipay_hosted_fields` to the `sylius_refund.supported_gateways` parameter — only when the Refund Plugin is installed. No manual configuration required.

### Refund flow

```
Sylius admin: issue refund
        ↓
RefundPaymentGenerated message dispatched (Sylius Refund Plugin)
        ↓
RefundPaymentGeneratedHandler::__invoke()
        ↓
Resolve order → payment → HiPay transaction reference
        ↓
HiPayClient::refundPayment(transactionReference, amount)
        ↓
PaymentRequest created (action: refund) + persisted
        ↓
payment.details['refund_response'] set + flush
```

**Handler:** [`src/RefundPlugin/Handler/RefundPaymentGeneratedHandler.php`](../src/RefundPlugin/Handler/RefundPaymentGeneratedHandler.php)

The handler:
1. Resolves the order by `orderNumber` from the message.
2. Finds the `PaymentMethod` — silently skips if it is not a HiPay gateway.
3. Retrieves the `transaction_reference` stored in `hipay_transaction` by `TransactionProvider`.
4. Calls `refundPayment($reference, $amount / 100)` (converts Sylius cents to decimals).
5. Creates and persists a `PaymentRequest` with `ACTION_REFUND`, sets it to complete, stores the raw response in `payment.details['refund_response']`.

### State resolvers

Two resolvers handle the Sylius order state after a refund:

| Resolver | File |
|----------|------|
| `OrderFullyRefundedStateResolver` | [`src/RefundPlugin/StateResolver/OrderFullyRefundedStateResolver.php`](../src/RefundPlugin/StateResolver/OrderFullyRefundedStateResolver.php) |
| `OrderPartiallyRefundedStateResolver` | [`src/RefundPlugin/StateResolver/OrderPartiallyRefundedStateResolver.php`](../src/RefundPlugin/StateResolver/OrderPartiallyRefundedStateResolver.php) |

These implement `Sylius\RefundPlugin\StateResolver\OrderStateResolverInterface` and are tagged automatically when the Refund Plugin is present.

---

## Prerequisites

Install `sylius/refund-plugin` in your Sylius application:

```bash
composer require sylius/refund-plugin
```

Follow the [Refund Plugin installation guide](https://github.com/Sylius/RefundPlugin) to run its migrations and register its bundle. The HiPay plugin integration activates automatically — no additional configuration needed.

---

## Webhook status mapping for refunds

HiPay sends refund-related notification codes that the plugin maps to the Sylius `refund` transition. See [hipay-status-mapping.md](./hipay-status-mapping.md) for the full table — notably codes `124` (RefundRequested), `125` (Refunded), `126` (PartiallyRefunded), and chargeback codes mapped to `refund`.

---

## What you get for free

| Feature | Detail |
|---------|--------|
| Gateway registration | `hipay_hosted_fields` added to `sylius_refund.supported_gateways` at compile time |
| HiPay API refund call | Triggered on `RefundPaymentGenerated`, amount converted from cents |
| `PaymentRequest` persistence | Refund request tracked in `hipay_payment_request` |
| Response stored on payment | `payment.details['refund_response']` available for inspection |
| Partial refund support | Handler processes any amount; HiPay API supports partial captures |
| Logging | All steps logged on the `hipay` channel — see [logging.md](./logging.md) |
