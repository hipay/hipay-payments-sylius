# On-demand (delayed) capture

For the **Card** payment product (Hosted Fields and Hosted Page), the gateway can be
configured to **authorize** the payment at checkout and **capture** it later, instead of
capturing immediately.

## Configuration

In the payment method gateway configuration, the **Capture mode** field offers:

- **Instant capture** — the payment is captured at checkout (`operation = Sale`).
- **On-demand capture** — the payment is only authorized at checkout
  (`operation = Authorization`); it must be captured later.

The choice is stored as the boolean `use_authorize` at the root of the gateway config,
which Sylius reads natively to create either a `capture`/`authorize` payment request.

## Capturing an authorized payment

An authorized payment stays in the Sylius `authorized` state until it is captured.

### From the back office

A **Capture** button appears on the payment row when the payment is eligible: Sylius
state `authorized` **and** latest HiPay status `Authorized` (116), and no capture has
already been requested. Clicking it sends the capture request to HiPay; the payment
**remains `authorized`** and only moves to `paid` when the HiPay `Captured` (118)
notification is received. A second capture request is prevented.

### From a custom workflow

Dispatch `RequestCaptureEvent` with the id of the payment to capture. The plugin listens
to it, performs the capture and sets the outcome (`CaptureRequestResult`) back on the
event:

```php
use HiPay\SyliusHiPayPlugin\Event\RequestCaptureEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

public function __construct(
    private readonly EventDispatcherInterface $eventDispatcher,
) {
}

$event = new RequestCaptureEvent($paymentId);
$this->eventDispatcher->dispatch($event);

if ($event->getResult()?->isSuccessful()) {
    // capture requested — the payment becomes "paid" on the HiPay notification
} else {
    $reason = $event->getResult()?->getError();
}
```

The amount is always read from the payment (the full payment amount); there is no amount
parameter.

### From the HiPay back office

A capture triggered directly on an "Authorized" transaction in the HiPay back office is
handled automatically: the `Captured` notification updates Sylius (no development needed).

## Limitations

Only **full, single** on-demand capture is supported. Partial and multiple captures are
**not** implemented: the Sylius `Payment` object and the workflow do not model partial
states. The full payment amount is always sent.
