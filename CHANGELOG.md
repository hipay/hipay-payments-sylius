# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v1.0.0] - 2026-04-27

## [1.0.0] - 2026-04-24

Initial public release of the HiPay payment plugin for Sylius 2.x.

### Added

#### Payment gateways and products

- HiPay **Hosted Fields** gateway for credit and debit cards (Visa, Mastercard, CB,
  American Express, Maestro, BCMC), implemented as a Symfony UX Live Component
  using the official HiPay JS SDK with SRI-aware Twig helpers and a dedicated
  Stimulus controller (`hipay-hosted-fields`).
- **Saved cards / one-click** payments via card tokenization, with a per-customer
  saved card store and a `HiPaySavedCard` entity managed through the
  Hosted Fields component.
- **Apple Pay** support, including Apple Pay domain verification and merchant
  capabilities configuration.
- **Bancontact** (BCMC) for the Belgian market.
- **iDEAL** for the Dutch market.
- **Multibanco** and **MB Way** for the Portuguese market, with a dedicated
  `hipay-multibanco` Stimulus controller.
- **PayPal** redirect flow.

#### Payment lifecycle

- Full integration with Sylius **PaymentRequest** pipeline through Messenger
  command handlers (`NewOrderRequest`, `TransactionInformationRequest`,
  state machine updates).
- 3DS challenge forwarding and post-payment redirects via a dedicated
  PaymentRequest HTTP response provider.
- **Manual capture** and **cancel** processors for authorized transactions.
- **Order lifecycle subscriber** that translates HiPay events into Sylius
  payment state machine transitions.
- Channel-aware **payment method resolver** that filters available HiPay
  products per channel and customer context.

#### Webhook processing (asynchronous)

- HMAC signature validation of HiPay server-to-server notifications via the
  Symfony Webhook component.
- Persistent **`hipay_pending_notification` buffer** so that the HTTP webhook
  endpoint always returns quickly and notifications are never lost when the
  checkout commit has not yet landed.
- **Priority-aware** notification ordering based on HiPay status code so that
  later events (e.g. capture) cannot overtake earlier ones (e.g. authorization)
  for the same transaction.
- **Symfony Scheduler worker** (`scheduler_hipay_notifications`) that drains
  the buffer in batches with `SKIP LOCKED` atomic claims, configurable tick
  interval, batch size, and maturity buffer.
- **Per-row retry with exponential backoff** (8 attempts by default), failure
  isolation so that one poisonous notification cannot block the queue, and
  stalled-claim recovery.
- Mapping of HiPay status codes to Sylius payment transitions and
  `PaymentRequest` actions, fully documented.

#### Refund

- Optional integration with **`sylius/refund-plugin`**: a refund issued from
  the Sylius admin triggers the HiPay refund API automatically on the
  `RefundPaymentGenerated` event, with response handling.

#### Sylius admin

- **HiPay Account** CRUD with per-account API credentials (username,
  password, passphrase), environment selection (Stage / Production), and
  per-account debug mode.
- Payment method configuration form rendered as a **Symfony UX Live
  Component**, with dynamic fields per gateway and per payment product.
- Currency and country **filtering** on gateway configuration to keep only
  combinations supported by HiPay.
- Admin menu entries and gateway configuration types for all supported
  payment products.

#### Fraud and compliance

- **Fraud suspicion** notification handler with a dedicated email manager
  for merchant alerts.
- **PII redaction** in logs through a dedicated `hipay` Monolog channel and a
  `redact-sensitive` integration that masks card data, holder names,
  passwords, and other sensitive fields.

#### Developer experience

- **Extensible payment product registry**: custom payment products can be
  added in a host application by registering a tagged service, without
  modifying the plugin (see `docs/add-payment-product.md`).
- **Domain events** for checkout, synchronous API calls, and the webhook
  lifecycle, allowing host applications to hook into HiPay flows without
  patching the plugin (see `docs/events.md`).
- Doctrine migrations for all plugin tables: `hipay_account`,
  `hipay_transaction`, `hipay_saved_card`, `hipay_pending_notification`.
- Symfony UX assets shipped through the package
  `@hipay/sylius-hipay-plugin`, with two Stimulus controllers
  (`hipay-hosted-fields`, `hipay-multibanco`).
- Full **English translations** for the shop and the admin.
- **Configurable parameters** for the webhook worker (interval, batch size,
  max attempts, retry delays, maturity buffer, stalled claim timeout).
- **Continuous integration** matrix on GitHub Actions covering Composer
  validation, ECS, PHPStan, PHPMD, Symfony lint (PHP 8.2 / 8.3 / 8.4),
  PHPUnit (unit, integration, functional), and Behat.

#### Documentation

- `README.md` — installation and configuration in a Sylius application.
- `docs/payment-workflow.md` — end-to-end payment flow.
- `docs/add-payment-product.md` — extending the plugin with new payment
  products.
- `docs/hipay-status-mapping.md` — HiPay status codes mapped to Sylius
  transitions.
- `docs/events.md` — domain events reference with listener examples.
- `docs/webhook-async-processing.md` — Scheduler worker, pending
  notification buffer, retry strategy, tuning, failure recovery.
- `docs/refund-plugin.md` — Sylius Refund Plugin integration.
- `docs/logging.md` — `hipay` log channel and PII redaction.
- `docs/content-security-policy.md` — CSP directives required by the
  Hosted Fields and Apple Pay flows.
- `docs/troubleshooting.md` — common issues and fixes.

### Compatibility

| Sylius        | PHP            | Symfony |
|---------------|----------------|---------|
| 2.0, 2.1, 2.2 | 8.2, 8.3, 8.4  | 7.4     |

## [1.0.0] - 2026-04-24

Initial public release of the HiPay payment plugin for Sylius 2.x.

### Added

#### Payment gateways and products

- HiPay **Hosted Fields** gateway for credit and debit cards (Visa, Mastercard, CB,
  American Express, Maestro, BCMC), implemented as a Symfony UX Live Component
  using the official HiPay JS SDK with SRI-aware Twig helpers and a dedicated
  Stimulus controller (`hipay-hosted-fields`).
- **Saved cards / one-click** payments via card tokenization, with a per-customer
  saved card store and a `HiPaySavedCard` entity managed through the
  Hosted Fields component.
- **Apple Pay** support, including Apple Pay domain verification and merchant
  capabilities configuration.
- **Bancontact** (BCMC) for the Belgian market.
- **iDEAL** for the Dutch market.
- **Multibanco** and **MB Way** for the Portuguese market, with a dedicated
  `hipay-multibanco` Stimulus controller.
- **PayPal** redirect flow.

#### Payment lifecycle

- Full integration with Sylius **PaymentRequest** pipeline through Messenger
  command handlers (`NewOrderRequest`, `TransactionInformationRequest`,
  state machine updates).
- 3DS challenge forwarding and post-payment redirects via a dedicated
  PaymentRequest HTTP response provider.
- **Manual capture** and **cancel** processors for authorized transactions.
- **Order lifecycle subscriber** that translates HiPay events into Sylius
  payment state machine transitions.
- Channel-aware **payment method resolver** that filters available HiPay
  products per channel and customer context.

#### Webhook processing (asynchronous)

- HMAC signature validation of HiPay server-to-server notifications via the
  Symfony Webhook component.
- Persistent **`hipay_pending_notification` buffer** so that the HTTP webhook
  endpoint always returns quickly and notifications are never lost when the
  checkout commit has not yet landed.
- **Priority-aware** notification ordering based on HiPay status code so that
  later events (e.g. capture) cannot overtake earlier ones (e.g. authorization)
  for the same transaction.
- **Symfony Scheduler worker** (`scheduler_hipay_notifications`) that drains
  the buffer in batches with `SKIP LOCKED` atomic claims, configurable tick
  interval, batch size, and maturity buffer.
- **Per-row retry with exponential backoff** (8 attempts by default), failure
  isolation so that one poisonous notification cannot block the queue, and
  stalled-claim recovery.
- Mapping of HiPay status codes to Sylius payment transitions and
  `PaymentRequest` actions, fully documented.

#### Refund

- Optional integration with **`sylius/refund-plugin`**: a refund issued from
  the Sylius admin triggers the HiPay refund API automatically on the
  `RefundPaymentGenerated` event, with response handling.

#### Sylius admin

- **HiPay Account** CRUD with per-account API credentials (username,
  password, passphrase), environment selection (Stage / Production), and
  per-account debug mode.
- Payment method configuration form rendered as a **Symfony UX Live
  Component**, with dynamic fields per gateway and per payment product.
- Currency and country **filtering** on gateway configuration to keep only
  combinations supported by HiPay.
- Admin menu entries and gateway configuration types for all supported
  payment products.

#### Fraud and compliance

- **Fraud suspicion** notification handler with a dedicated email manager
  for merchant alerts.
- **PII redaction** in logs through a dedicated `hipay` Monolog channel and a
  `redact-sensitive` integration that masks card data, holder names,
  passwords, and other sensitive fields.

#### Developer experience

- **Extensible payment product registry**: custom payment products can be
  added in a host application by registering a tagged service, without
  modifying the plugin (see `docs/add-payment-product.md`).
- **Domain events** for checkout, synchronous API calls, and the webhook
  lifecycle, allowing host applications to hook into HiPay flows without
  patching the plugin (see `docs/events.md`).
- Doctrine migrations for all plugin tables: `hipay_account`,
  `hipay_transaction`, `hipay_saved_card`, `hipay_pending_notification`.
- Symfony UX assets shipped through the package
  `@hipay/sylius-hipay-plugin`, with two Stimulus controllers
  (`hipay-hosted-fields`, `hipay-multibanco`).
- Full **English translations** for the shop and the admin.
- **Configurable parameters** for the webhook worker (interval, batch size,
  max attempts, retry delays, maturity buffer, stalled claim timeout).
- **Continuous integration** matrix on GitHub Actions covering Composer
  validation, ECS, PHPStan, PHPMD, Symfony lint (PHP 8.2 / 8.3 / 8.4),
  PHPUnit (unit, integration, functional), and Behat.

#### Documentation

- `README.md` — installation and configuration in a Sylius application.
- `docs/payment-workflow.md` — end-to-end payment flow.
- `docs/add-payment-product.md` — extending the plugin with new payment
  products.
- `docs/hipay-status-mapping.md` — HiPay status codes mapped to Sylius
  transitions.
- `docs/events.md` — domain events reference with listener examples.
- `docs/webhook-async-processing.md` — Scheduler worker, pending
  notification buffer, retry strategy, tuning, failure recovery.
- `docs/refund-plugin.md` — Sylius Refund Plugin integration.
- `docs/logging.md` — `hipay` log channel and PII redaction.
- `docs/content-security-policy.md` — CSP directives required by the
  Hosted Fields and Apple Pay flows.
- `docs/troubleshooting.md` — common issues and fixes.

### Compatibility

| Sylius        | PHP            | Symfony |
|---------------|----------------|---------|
| 2.0, 2.1, 2.2 | 8.2, 8.3, 8.4  | 7.4     |