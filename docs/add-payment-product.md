# Adding a new HiPay payment product

This guide walks through **extending SyliusHiPayPlugin** with another HiPay product (e.g. wallet, BNPL) while reusing shared infrastructure (webhooks, HMAC, Payment Request bus).

**Prerequisites:** read [payment-workflow.md](./payment-workflow.md) sections **D** (processors) and **F** (payment product handlers).

**Official HiPay docs**

- [Payment products](https://developer.hipay.com/payment-fundamentals/essentials/payment-methods)
- [Order API / request fields](https://developer.hipay.com/doc/hipay-order-api/)
- [Hosted Fields / JS SDK](https://developer.hipay.com/doc/hipay-hosted-fields/)

## Table of contents

1. [Create the payment product handler](#1-create-the-payment-product-handler)
2. [Register the handler service](#2-register-the-handler-service)
3. [Add product-specific processors](#3-add-product-specific-processors)
4. [Create a builder and tag it](#4-create-a-builder-and-tag-it)
5. [Reuse processors with double-tagging](#5-reuse-processors-with-double-tagging)
6. [Templates: Twig hooks, LiveComponent, assets](#6-templates-twig-hooks-livecomponent-assets)
7. [Optional: admin `FormType`](#7-optional-admin-formtype)
8. [Optional: `PluginPaymentProduct` enum](#8-optional-pluginpaymentproduct-enum)
9. [Checklist: what you get “for free”](#9-checklist-what-you-get-for-free)
10. [Full example sketch (handler + `services.php`)](#10-full-example-sketch-handler-servicesphp)

---

## 1. Create the payment product handler

Implement [`PaymentProductHandlerInterface`](../src/PaymentProduct/PaymentProductHandlerInterface.php). Extend [`AbstractPaymentProductHandler`](../src/PaymentProduct/Handler/AbstractPaymentProductHandler.php) when `supports()` can default to matching `getCode()`.

**Responsibilities**

| Concern | Where |
|---------|--------|
| Admin labels / form | `getName()`, `getFormType()` |
| JS SDK `configuration` | `getJsInitConfig(PaymentMethodInterface)` — must match what your Stimulus / Hosted Fields flow expects |
| Product code matching | `supports(string $paymentProduct)` — HiPay API may send `visa`, `paypal`, etc. |
| Checkout restrictions (admin) | `getAvailableCountries()`, `getAvailableCurrencies()` — empty = no restriction in `GeneralConfigurationType` |

**Reference:** [`src/PaymentProduct/Handler/CardHandler.php`](../src/PaymentProduct/Handler/CardHandler.php).

**Front-end:** if the new product still uses Hosted Fields, you may reuse [`hipay_bridge_controller.js`](../assets/controllers/hipay_bridge_controller.js) and [`HiPayCheckoutComponent`](../src/Twig/Component/Shop/HiPayCheckoutComponent.php). If the SDK contract differs, add a dedicated Stimulus controller or branch inside the bridge and ensure `processPayment` still writes **`Payment::details`** in a shape your processors understand.

---

## 2. Register the handler service

In your app or a compiler pass, register the class with the tag **`sylius_hipay_plugin.payment_product_handler`** and attribute **`code`** equal to **`getCode()`** return value.

**Plugin example** ([`config/services.php`](../config/services.php)):

```php
$services->set('sylius_hipay_plugin.payment_product.handler.card', CardHandler::class)
    ->args([
        service(CustomerContextInterface::class),
        service('sylius_hipay_plugin.repository.saved_card'),
    ])
    ->tag('sylius_hipay_plugin.payment_product_handler', ['code' => 'card']);
```

The registry [`PaymentProductHandlerRegistry`](../src/PaymentProduct/PaymentProductHandlerRegistry.php) uses:

- **tagged_locator** keyed by `code` → `get($code)`
- **tagged_iterator** for `getForPaymentProduct()` (first `supports()` match)

---

## 3. Add product-specific processors

Create classes implementing [`PaymentOrderRequestProcessorInterface`](../src/PaymentOrderRequest/PaymentOrderRequestProcessorInterface.php). Read only from **`PaymentOrderRequestContext`** and mutate **`OrderRequest`**.

**Shared processors** (card pipeline) live under [`src/PaymentOrderRequest/Processor/`](../src/PaymentOrderRequest/Processor/). Typical reuse:

- **`CommonFieldsProcessorPayment`** — amount, currency, operation
- **`CustomerBillingInfoProcessorPayment`**
- **`BrowserInfoProcessorPayment`** — needs `payload` + `RequestStack`
- **`CallbackUrlsProcessorPayment`** — `notify_url` and return URLs

Product-specific processors set **`paymentMethod`**, **`payment_product`**, or other HiPay fields required for your product.

---

## 4. Create a builder and tag it

[`PaymentOrderRequestBuilder`](../src/PaymentOrderRequest/PaymentOrderRequestBuilder.php) receives:

1. **Product code string** — must match **`gatewayConfig['payment_product']`** for this gateway method (see context factory).
2. **Tagged iterator of processors** — order by Symfony tag **priority** (higher runs first with `defaultPriorityMethod: '__none__'`).

Register:

- Tag **`sylius_hipay_plugin.order_request_builder`** with **`code`** same as the first constructor argument.
- Tag each processor with a **product-specific** tag name, e.g. `sylius_hipay_plugin.order_request_processor.my_product`.

**Plugin pattern** (card):

```php
$services->set('sylius_hipay_plugin.payment_order_request.processor.common_fields', CommonFieldsProcessorPayment::class)
    ->args([service('clock')])
    ->tag('sylius_hipay_plugin.order_request_processor.card', ['priority' => 50]);

// ... more processors ...

$services->set('sylius_hipay_plugin.payment_order_request.order_request.card', PaymentOrderRequestBuilder::class)
    ->args([
        'card',
        tagged_iterator('sylius_hipay_plugin.order_request_processor.card', defaultPriorityMethod: '__none__'),
    ])
    ->tag('sylius_hipay_plugin.order_request_builder', ['code' => 'card']);
```

[`PaymentOrderRequestBuilderRegistry`](../src/PaymentOrderRequest/PaymentOrderRequestBuilderRegistry.php) picks the first builder where **`supports($context->paymentProduct)`** is true — default implementation compares **strict equality** with the builder’s internal code.

---

## 5. Reuse processors with double-tagging

To attach the same processor class to **card** and **another** pipeline, call **`->tag()` twice** on the service definition (or define an abstract prototype). Each tag name corresponds to one `tagged_iterator` in a builder.

---

## 6. Templates: Twig hooks, LiveComponent, assets

Shop UI is **not** auto-discovered from the PHP handler alone. For **`hipay_hosted_fields`**, the plugin wires Sylius **Twig Hooks** so the checkout page loads the SDK script, optional CSS, payment-method labels, and the **LiveComponent** that mounts the Stimulus bridge.

### 6.1 Twig Hooks configuration (plugin)

**File:** [`config/twig_hooks/shop/checkout.yaml`](../config/twig_hooks/shop/checkout.yaml)

| Hook name | Purpose |
|-----------|---------|
| `sylius_shop.checkout#stylesheets` | Adds [`templates/shop/checkout/stylesheets/css.html.twig`](../templates/shop/checkout/stylesheets/css.html.twig) (`.hipay-form-container`, etc.). |
| `sylius_shop.checkout#javascripts` | Injects HiPay SDK `<script>` via [`templates/shop/checkout/javascripts/hipay_js_sdk.html.twig`](../templates/shop/checkout/javascripts/hipay_js_sdk.html.twig) (`hipay_js_sdk_url()`, `hipay_integrity_hash()` from [`HipayExtension`](../src/Twig/Extension/HipayExtension.php)). |
| `sylius_shop.checkout.select_payment.content.form.select_payment.payment.choice.hipay_hosted_fields` | Renders payment method “details” wrapper ([`.../hipay_hosted_fields/details.html.twig`](../templates/shop/checkout/select_payment/content/form/select_payment/payment/choice/hipay_hosted_fields/details.html.twig)). |
| `...hipay_hosted_fields.details` | **Name** + **description** partials for the method row. |
| `...hipay_hosted_fields.details.card` | Optional card-specific name hook. |
| `sylius_shop.checkout.select_payment.content.form` | **Navigation** override ([`navigation.html.twig`](../templates/shop/checkout/select_payment/content/form/navigation.html.twig)) — e.g. delays “Next” until HiPay has run when gateway is `hipay_hosted_fields`. |

**In your application:** import the plugin’s hook config (or copy the entries) so these templates run on checkout. To customize layout, **override** the same hook names in `config/twig_hooks/` and point `template:` to your bundle paths.

### 6.2 Where the LiveComponent is rendered

**File:** [`templates/shop/checkout/select_payment/content/form/select_payment/payment/choice/hipay_hosted_fields/details/description.html.twig`](../templates/shop/checkout/select_payment/content/form/select_payment/payment/choice/hipay_hosted_fields/details/description.html.twig)

When the selected gateway is **`hipay_hosted_fields`** and the row matches the selected method, it outputs:

```twig
{{ component('hipay_checkout', {paymentMethod: selectedMethod, payment}) }}
```

- **Component name:** `hipay_checkout` (see `#[AsLiveComponent(name: 'hipay_checkout')]` on [`HiPayCheckoutComponent`](../src/Twig/Component/Shop/HiPayCheckoutComponent.php)).
- **Service / template:** registered in [`config/services.php`](../config/services.php) as `sylius_hipay_plugin.twig.component.shop.hipay_checkout` with `template => '@SyliusHiPayPlugin/components/hipay_checkout.html.twig'`.

### 6.3 LiveComponent Twig template

**File:** [`templates/components/hipay_checkout.html.twig`](../templates/components/hipay_checkout.html.twig)

- Root element: **`stimulus_controller('hipay-bridge', { initialConfig: this.getJsSdkConfig()|json_encode })`** — passes server-built config (credentials + `getJsInitConfig()`) to [`hipay_bridge_controller.js`](../assets/controllers/hipay_bridge_controller.js).
- DOM targets: loader, error banner, **`#hipay-hostedfields-form`** (SDK mount), pay button.

For a **new product** that still uses this bridge: adjust **`getJsInitConfig()`** (and optionally CSS in `stylesheets/css.html.twig`). The **HTML shell** can stay the same if the SDK still uses a container + button pattern.

### 6.4 When you need a different UI flow

| Scenario | What to change |
|----------|----------------|
| Same gateway, different Hosted Fields layout | Override `hipay_checkout.html.twig` in your theme/bundle (same component name) or register a **new** LiveComponent + template and call `component('your_name', …)` from your override of `description.html.twig`. |
| New gateway factory name (not `hipay_hosted_fields`) | Add **new** Twig Hook entries mirroring `hipay_hosted_fields` (choice + details + description), register **Payment Request** providers/command provider for that factory in `services.php`, and ensure checkout navigation still matches your JS submit flow. |
| Different JS integration | New Stimulus controller; still call a LiveComponent **`processPayment`-style** action that **`$payment->setDetails(...)`** so processors keep reading `context.payload`. |

### 6.5 Front-end build

Ensure the Stimulus controller **`hipay-hosted-fields`** is compiled (plugin `assets/controllers/hipay_hosted_fields_controller.js`). In a host app, register it in `assets/controllers.json` under `@hipay/hipay-payments-sylius` so `hipay-hosted-fields` resolves at runtime (see `assets/shop/controllers.json` for the reference entry).

---

## 7. Optional: admin `FormType`

If the product needs extra gateway options, return a form class from **`getFormType()`**. Extend or embed [`GeneralConfigurationType`](../src/Form/Type/Gateway/PaymentProductConfiguration/GeneralConfigurationType.php) for **account**, **payment_product**, **allowed_countries**, **allowed_currencies**, amounts, 3DS, etc.

Wire the form in [`HostedFieldsConfigurationType`](../src/Form/Type/Gateway/HostedFieldsConfigurationType.php) or your gateway configuration type if you introduce a new factory name.

---

## 8. Optional: `PluginPaymentProduct` enum

For **labels** and API product code normalization, add a case to [`PluginPaymentProduct`](../src/PaymentProduct/PluginPaymentProduct.php) and update [`PaymentProductProvider`](../src/Provider/PaymentProductProvider.php) / [`CardPaymentProduct`](../src/PaymentProduct/CardPaymentProduct.php)-style helpers if you map HiPay catalog codes to a single plugin “family”.

This enum is **not** required for runtime resolution of handlers — tags and `supports()` are.

---

## 9. Checklist: what you get “for free”

| Feature | Notes |
|---------|--------|
| **Webhook + HMAC** | [`RequestParser`](../src/Webhook/RequestParser.php) validates all notifications; no per-product code |
| **Status → Sylius** | [`HiPayStatus`](../src/Payment/HiPayStatus.php) maps numeric status to payment transitions — extend only if HiPay adds codes |
| **Payment methods resolver** | [`PaymentMethodsResolver`](../src/Resolver/PaymentMethodsResolver.php) filters by gateway **configuration** (countries, currencies, amounts) |
| **Transaction persistence** | `NewOrderRequestHandler` saves [`Transaction`](../src/Entity/Transaction.php) after `requestNewOrder` |
| **Redirect after pay** | [`HostedFieldsHttpResponseProvider`](../src/OrderPay/Provider/HostedFieldsHttpResponseProvider.php) — adjust if new products need different URLs |
| **Events** | Dedicated classes (`AfterWebhookNotificationProcessedEvent`, checkout events, …) — see [events.md](./events.md) |

---

## 10. Full example sketch (handler + `services.php`)

**Handler (minimal)**

```php
<?php

declare(strict_types=1);

namespace App\HiPay\PaymentProduct;

use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\AbstractPaymentProductHandler;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class MyWalletHandler extends AbstractPaymentProductHandler
{
    public function getCode(): string
    {
        return 'my_wallet';
    }

    public function getName(): string
    {
        return 'app.hipay.payment_product.my_wallet';
    }

    public function supports(string $paymentProduct): bool
    {
        return \in_array($paymentProduct, ['my-wallet', 'my_wallet'], true);
    }

    public function getJsInitConfig(PaymentMethodInterface $paymentMethod): array
    {
        return [
            'template' => 'bootstrap-3',
            'fields' => [],
            'custom' => [],
        ];
    }
}
```

**Services (fragment)**

```php
use App\HiPay\PaymentProduct\MyWalletHandler;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestBuilder;
use App\HiPay\PaymentOrderRequest\MyWalletPaymentMethodProcessor;

$services->set('app.hipay.payment_product.handler.my_wallet', MyWalletHandler::class)
    ->tag('sylius_hipay_plugin.payment_product_handler', ['code' => 'my_wallet']);

$services->set('app.hipay.payment_order_request.processor.my_wallet_method', MyWalletPaymentMethodProcessor::class)
    ->tag('sylius_hipay_plugin.order_request_processor.my_wallet', ['priority' => 10]);

$services->set('app.hipay.payment_order_request.builder.my_wallet', PaymentOrderRequestBuilder::class)
    ->args([
        'my_wallet',
        tagged_iterator('sylius_hipay_plugin.order_request_processor.my_wallet', defaultPriorityMethod: '__none__'),
    ])
    ->tag('sylius_hipay_plugin.order_request_builder', ['code' => 'my_wallet']);
```

**Gateway config:** set **`payment_product`** to **`my_wallet`** for the Sylius payment method using this pipeline.

**Verification**

1. Admin: payment method saves; checkout shows component when gateway is `hipay_hosted_fields` (or your variant).
2. Browser: SDK init receives `getJsInitConfig` output.
3. After submit: `Payment::details` populated; `NewOrderRequestHandler` runs; HiPay order request includes fields from your processors.
4. Webhook: same endpoint; status updates via `HiPayStatus`.

---

## See also

- [payment-workflow.md](./payment-workflow.md)
- [hipay-status-mapping.md](./hipay-status-mapping.md)
