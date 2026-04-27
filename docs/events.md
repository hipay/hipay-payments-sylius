# Domain events (SyliusHiPayPlugin)

Symfony dispatches these objects on the **default event name** (the event class FQCN). Subscribe with `EventSubscriberInterface` using `YourEvent::class => 'methodName'`, or `#[AsEventListener]` targeting the event class.

**Related:** [payment-workflow.md](./payment-workflow.md)

---

## Checkout (shop LiveComponent)

| Event | When | Typical use |
|-------|------|-------------|
| [`CheckoutSdkConfigResolvedEvent`](../src/Event/CheckoutSdkConfigResolvedEvent.php) | After building the HiPay JS SDK bootstrap array in [`HiPayCheckoutComponent::getJsSdkConfig()`](../src/Twig/Component/Shop/HiPayCheckoutComponent.php) | Adjust `lang`, `debug`, or nested `configuration` without overriding the LiveComponent. |
| [`CheckoutPaymentDetailsDecodedEvent`](../src/Event/CheckoutPaymentDetailsDecodedEvent.php) | After JSON decode, before `Payment::setDetails()` | Normalize or validate SDK payload. |
| [`CheckoutPaymentDetailsPersistedEvent`](../src/Event/CheckoutPaymentDetailsPersistedEvent.php) | After `flush()` in `processPayment()` | Server-side audit once details are stored (browser still receives `hipay:payment:processed`). |

**Listener example — add a custom field to the SDK config:**

```php
use HiPay\SyliusHiPayPlugin\Event\CheckoutSdkConfigResolvedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class CustomSdkConfigListener
{
    public function __invoke(CheckoutSdkConfigResolvedEvent $event): void
    {
        $config = $event->getConfig();
        $config['configuration']['custom_css_url'] = 'https://example.com/hipay.css';
        $event->setConfig($config);
    }
}
```

---

## Payment Request → HiPay API

| Event | When | Typical use |
|-------|------|-------------|
| [`BeforeOrderRequestEvent`](../src/Event/BeforeOrderRequestEvent.php) | After processors built the HiPay `OrderRequest`, before `requestNewOrder()` ([`NewOrderRequestHandler`](../src/CommandHandler/NewOrderRequestHandler.php)) | Mutate `OrderRequest`, or set `alternativeResponseData` to skip the API call (tests, custom gateways). |
| [`AfterPaymentProcessedEvent`](../src/Event/AfterPaymentProcessedEvent.php) | After `PaymentRequest::responseData` is set, before Sylius payment / payment-request state updates | Mutate `responseData` (e.g. enrich for `HostedFieldsHttpResponseProvider`). Optional custom `Response` is reserved for future HTTP-layer integration. |

`AfterPaymentProcessedEvent` is also dispatched from [`TransactionInformationRequestHandler`](../src/CommandHandler/TransactionInformationRequestHandler.php) after `requestTransactionInformation()`.

**Listener example — add a custom field to the order request:**

```php
use HiPay\SyliusHiPayPlugin\Event\BeforeOrderRequestEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class CustomOrderRequestListener
{
    public function __invoke(BeforeOrderRequestEvent $event): void
    {
        $event->getOrderRequest()->custom_data = json_encode(['source' => 'my-app']);
    }
}
```

---

## Webhook (asynchronous notifications)

| Event | When | Typical use |
|-------|------|-------------|
| [`BeforeWebhookNotificationProcessedEvent`](../src/Event/BeforeWebhookNotificationProcessedEvent.php) | Payment resolved from `transaction_reference`, before creating a webhook `PaymentRequest` and applying transitions | Logging, metrics; `stopPropagation()` does not skip core processing (processor does not check propagation). |
| [`AfterWebhookNotificationProcessedEvent`](../src/Event/AfterWebhookNotificationProcessedEvent.php) | After a successful transition and `flush`, or after the “cannot apply transition” path flushed a cancelled webhook request | Same as existing plugin subscribers (saved card, refund plugin). **Not** dispatched when no payment is found (exception before “before” event) or when the transition branch returns early without the final success flush—see [`NotificationProcessor`](../src/Webhook/NotificationProcessor.php). |

**Listener example — send a Slack alert on capture:**

```php
use HiPay\SyliusHiPayPlugin\Event\AfterWebhookNotificationProcessedEvent;
use HiPay\SyliusHiPayPlugin\Payment\HiPayStatus;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class CaptureAlertListener
{
    public function __invoke(AfterWebhookNotificationProcessedEvent $event): void
    {
        if ($event->getStatus() === HiPayStatus::Captured->value) {
            // send your alert…
        }
    }
}
```

---

## HiPay reference

- [Notifications](https://developer.hipay.com/payment-fundamentals/requirements/notifications)
- [Transaction status](https://developer.hipay.com/payment-fundamentals/essentials/transaction-status)
