# Sylius HiPay Plugin

[![PHP](https://img.shields.io/badge/php-%5E8.2-8892BF)](https://www.php.net/)
[![Sylius](https://img.shields.io/badge/Sylius-~2.0-blue)](https://sylius.com/)
[![GitHub license](https://img.shields.io/badge/license-Apache%202-blue.svg)](LICENSE.md)

**HiPay** integration for **Sylius 2.x**: **Hosted Fields** checkout (Symfony UX **Live Component** + HiPay JS SDK), **Sylius Payment Request** pipeline, **Order API** built through configurable **processors**, **signed webhooks** (Symfony Webhook), and optional **Refund Plugin** integration.

Composer package: [`hipay/hipay-payments-sylius`](https://packagist.org/packages/hipay/hipay-payments-sylius)

## Compatibility

| Sylius Version | PHP Version  | Symfony Version |
|----------------|--------------|-----------------|
| 2.0, 2.1, 2.2  | 8.2 - 8.3 - 8.4 | 7.4          |

> Node.js 22.x (see `.nvmrc`) is required only to build Stimulus / Encore assets in the **test application**, not in your Sylius application itself.

## Installation in a Sylius application

### 1. Install the package

```bash
composer require hipay/hipay-payments-sylius
```

### 2. Register the bundle

If not picked up automatically, add it to `config/bundles.php`:

```php
<?php

return [
    // ...
    HiPay\SyliusHiPayPlugin\SyliusHiPayPlugin::class => ['all' => true],
];
```

### 3. Import the plugin configuration

Create `config/packages/sylius_hipay_plugin.yaml`:

```yaml
imports:
    - { resource: "@SyliusHiPayPlugin/config/config.yaml" }
```

### 4. Import the routes

Create `config/routes/sylius_hipay_plugin.yaml`:

```yaml
sylius_hipay_resource_routes:
    resource: "sylius.symfony.routing.loader.resource"
    type: service
    prefix: '/%sylius_admin.path_name%'

sylius_hipay_plugin_webhook:
    path: /payment/hipay/notify
    controller: 'webhook.controller::handle'
    methods: ['POST']
    defaults:
        type: 'hipay'
```

### 5. Run database migrations

The plugin ships Doctrine migrations for its own tables (`hipay_account`, `hipay_transaction`, `hipay_saved_card`, `hipay_pending_notification`):

```bash
php bin/console doctrine:migrations:migrate
```

### 6. Run the HiPay webhook worker

HiPay notifications are buffered in `hipay_pending_notification` and applied by a Symfony Scheduler worker. Run one instance per application environment (systemd unit, Supervisor, container, etc.):

```bash
php bin/console messenger:consume scheduler_hipay_notifications --time-limit=3600 --memory-limit=128M
```

The defaults (30s tick, 180s buffer, 50 rows per batch, 8 retries with exponential backoff) work out of the box. You may override them in your application by redefining the relevant plugin parameters — see [docs/webhook-async-processing.md](docs/webhook-async-processing.md).

### 7. Install assets

Copy plugin assets to your application's `public/` directory:

```bash
php bin/console assets:install
```

The plugin exposes two Stimulus controllers (`hipay-hosted-fields`, `hipay-multibanco`) via the Symfony UX package `@hipay/sylius-hipay-plugin`. Enable them in your application's `assets/controllers.json`:

```json
{
    "controllers": {
        "@hipay/sylius-hipay-plugin": {
            "hipay-hosted-fields": {
                "enabled": true,
                "fetch": "eager"
            },
            "hipay-multibanco": {
                "enabled": true,
                "fetch": "eager"
            }
        }
    }
}
```

Then rebuild your front-end assets:

```bash
# Webpack Encore
yarn encore dev

# or AssetMapper
php bin/console importmap:install
```

### 8. Configure a HiPay gateway

In the Sylius admin panel:

1. Go to **Configuration → Payment Methods** and create a new payment method.
2. Select a HiPay gateway (e.g. `HiPay Hosted Fields`).
3. Under **Configuration → HiPay → Accounts**, create a HiPay account with your API credentials (username, password, passphrase, environment).
4. Link the account to your payment method.

For architecture details and customization, start with **[docs/payment-workflow.md](docs/payment-workflow.md)** and **[docs/add-payment-product.md](docs/add-payment-product.md)**.

## Documentation

| Document | Description |
|----------|-------------|
| [Payment workflow](docs/payment-workflow.md) | End-to-end flow: checkout, Live Component, Payment Request, processors, redirects, webhooks, payment product handlers. |
| [Add a payment product](docs/add-payment-product.md) | Extending the plugin: handlers, DI tags, processors, builders, Twig hooks, optional admin forms. |
| [HiPay status mapping](docs/hipay-status-mapping.md) | HiPay notification status codes → Sylius payment transitions and PaymentRequest actions. |
| [Domain events](docs/events.md) | Class-based events for checkout, API calls, and webhooks — with listener examples. |
| [Webhook asynchronous processing](docs/webhook-async-processing.md) | Symfony Scheduler worker, `hipay_pending_notification` buffer, priority ordering, retry strategy, tuning parameters, failure recovery. |
| [Refund Plugin integration](docs/refund-plugin.md) | Automatic HiPay API refund via `sylius/refund-plugin`. |
| [Logging and PII redaction](docs/logging.md) | Dedicated `hipay` log channel, redacted fields, per-account debug mode. |
| [Content Security Policy](docs/content-security-policy.md) | CSP directives to whitelist when your application enforces a Content Security Policy. |
| [Troubleshooting](docs/troubleshooting.md) | Common issues: webhooks, Hosted Fields, Apple Pay, payment transitions, refunds. |

Official HiPay documentation (notifications, transaction status, Hosted Fields) is linked from these pages where relevant.

## Features

- **Shop**: `hipay_hosted_fields` gateway, Live Component `hipay_hosted_fields`, Stimulus `hipay-hosted-fields`, HiPay SDK loaded with SRI-aware Twig helpers.
- **Payment Request**: Messenger handlers (`NewOrderRequest`, `TransactionInformationRequest`), HTTP response provider for 3DS forwarding and post-payment redirects.
- **Order request**: `PaymentOrderRequestBuilder` + tagged **processors** per payment product (card pipeline included; extensible).
- **Webhooks**: HMAC-validated payloads, `NotificationProcessor`, mapping via `HiPayStatus` to Sylius state machine.
- **Admin**: HiPay **Account** CRUD, gateway configuration types, admin Live Component for payment method forms.
- **Extensions**: Dedicated Symfony event classes for checkout, synchronous API handling, and webhook lifecycle — see [events.md](docs/events.md).
- **Refund Plugin**: Automatic integration with `sylius/refund-plugin` — HiPay refund API called on `RefundPaymentGenerated` — see [refund-plugin.md](docs/refund-plugin.md).

### Supported payment products

| Product | Code | Availability |
|---------|------|-------------|
| Card (Visa, Mastercard, CB, Amex, Maestro, BCMC) | `card` | All |
| PayPal | `paypal` | All |
| Apple Pay | `apple-pay` | All |
| iDEAL | `ideal` | NL |
| Bancontact | `bcmc` | BE |
| Multibanco | `multibanco` | PT |
| MB Way | `mbway` | PT |
| Oney (BNPL) | `oney` | FR |

Additional products can be added without modifying the plugin — see [docs/add-payment-product.md](docs/add-payment-product.md).

## Developing this repository

The Sylius **test application** used for development and CI lives under `tests/TestApplication/`.

### Prerequisites

- [Symfony CLI](https://symfony.com/download)
- PHP **8.2+** (see `.php-version` if present)
- **Node.js** & **Yarn** (Node **22.x** per `.nvmrc`)
- **Docker** (optional; used by `.symfony.local.yaml` for database services)

Verify your environment:

```bash
make check
```

### Bootstrap

Copy environment files for the test application (e.g. from `.env` / `.env.local` examples) under `tests/TestApplication/`, then:

```bash
make install
```

This typically runs prerequisite checks, `composer install`, starts the Symfony server (and Docker services when configured), creates the database, runs migrations, loads fixtures, and builds front-end assets. See the [Makefile](Makefile) for the exact sequence.

Useful commands:

```bash
make up          # Start Symfony server
make stop        # Stop Symfony server
make cc          # Clear Symfony cache
make clean       # Drop DB, stop server, remove vendor/ node_modules/ var/ (destructive)
make test.all    # ECS, PHPStan, PHPMD, PHPUnit, Behat, YAML/Twig/container linters
```

Individual test targets: `make test.phpunit.unit`, `make test.phpunit.integration`, `make test.phpunit.functional`, `make test.behat`, `make test.phpstan`, `make test.ecs`, etc.

### Local HTTPS and webhooks

For HiPay server-to-server notifications during local development, you need a **public HTTPS URL** (e.g. a tunneling tool). Configure the tunnel and set any required tokens in your `.env` files for the test application.

For **Xdebug**, use your platform’s PHP extension packages and start PHP with `XDEBUG_MODE=debug` when needed (see Symfony and PHP documentation).

## Source layout

```
src/
├── Client/                 # HiPay SDK client resolution per Account
├── Command/                # Payment Request bus commands
├── CommandHandler/         # New order, transaction info, state machine updates
├── CommandProvider/        # Maps gateway actions to Messenger commands
├── Entity/                 # Account, Transaction, SavedCard
├── Event/                  # Domain events (checkout, API, webhook)
├── EventListener/          # Admin menu, workflow decorators
├── EventSubscriber/        # Order lifecycle, webhook-driven behaviour
├── Factory/                # Saved cards, etc.
├── Fixture/                # Sample Account fixtures
├── Form/                   # Admin Account and gateway / product configuration
├── Logging/                # Dedicated HiPay log channel
├── Mailer/                 # Fraud suspicion notifications
├── Migrations/             # Doctrine migrations
├── OrderPay/Provider/      # PaymentRequest HTTP response (redirects)
├── Payment/                # HiPay status mapping, Sylius transitions
├── PaymentOrderRequest/    # Context factory, builders, processors
├── PaymentProduct/         # Product handlers + registry (card, extensible)
├── Processor/              # Cancel, manual capture, notification side effects
├── Provider/               # Account, transaction, payment request, products
├── RefundPlugin/           # Optional Sylius Refund Plugin integration
├── Resolver/               # Channel-based payment method filtering
├── ThreeDS/                # 3DS mode value object
├── Twig/                   # Live Components, HiPay Twig extension
├── Validator/              # Webhook HMAC validation
└── Webhook/                # Request parser, consumer, NotificationProcessor
```

## Quality

The project uses **ECS** (coding standard), **PHPStan**, **PHPMD**, **PHPUnit**, and **Behat**. Run `make test.all` before opening a pull request.

## License

Apache License — see [LICENSE](LICENSE).
