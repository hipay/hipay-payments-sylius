# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v1.0.1] - 2026-05-05

## [1.0.1] - 2026-05-05

Stability release covering integration regressions surfaced on Sylius 2.2,
fresh-install friction, and admin form crashes when used alongside other
payment plugins.

### Fixed

- Container compilation failure on the very first `composer require` —
  the bundle now self-registers the dedicated `hipay` Monolog channel via
  `prependExtensionConfig()`, so `cache:clear` does not blow up with
  *"non-existent service `monolog.logger.hipay`"* before the user has had
  a chance to import the plugin's config.
- Admin "create payment method" page crashing with *"A factory name is
  required."* (Sylius core) or *"Call to a member function
  `getFactoryName()` on null"* (sylius/paypal-plugin's
  `PaymentMethodTypeExtension`) when a brand-new HiPay payment method is
  rendered. The Live Component now ensures a `GatewayConfig` is attached
  with the factory name read from the resource, the dehydrated LiveProp,
  or the `factory` route attribute, before the form is built.
- Checkout select-payment page crashing with *"The 'channels' property on
  component HostedFieldsComponent is missing its property-type. Add the
  Doctrine\\ORM\\PersistentCollection type"* on Sylius 2.x. The
  `paymentMethod` and `payment` LiveProps are now serialized by ID and
  reloaded from the EntityManager on hydration, side-stepping the
  recursive entity-traversal performed by the LiveComponent default.
- Hosted Fields SDK no longer fails to mount with
  *"HIPAY_SELECTOR_MUST_BE_EMPTY"* and the misleading *"This payment
  method could not be loaded (timeout)"* banner on Sylius 2.2. The fix
  layers `data-live-ignore` on each placeholder template (card, PayPal,
  Apple Pay) so morphdom preserves the SDK iframes across LiveComponent
  re-renders, plus a synchronous `data-hipay-mounted` flag and a
  module-level `WeakMap` to share the live SDK instance across the
  multiple Stimulus controllers that Sylius 2.2's UX bump now spawns
  on re-render.
- Pay-button click no longer surfaces *"Cannot read properties of null
  (reading 'getPaymentData')"* when the click is routed to a sibling
  controller that did not own the SDK instance. `submitPayment()` now
  resolves the live instance lazily from the shared map.

### Changed

- npm package renamed from `@hipay/sylius-hipay-plugin` to
  `@hipay/hipay-payments-sylius` to match the published Composer name
  (`hipay/hipay-payments-sylius`). The previous name caused
  `yarn install` to silently fail to link the package, and Stimulus
  Bridge to error with *"file `@hipay/...package.json` could not be
  found"* during `yarn encore dev`.
- README chapter "Install front-end assets" rewritten to walk integrators
  through the actual flow: declaring the npm package as a `file:`
  devDependency in their `package.json`, running `yarn install`, then
  enabling the controllers in `assets/controllers.json` before the
  build. The previous wording skipped the devDependency step entirely.
- HiPay SDK ready timeout raised from 3 s to 30 s. Three seconds was
  shorter than the cold-start cost of `sdkjs.js` plus its sub-chunks
  plus the iframe negotiation with `*.hipay-tpp.com`, which surfaced
  false-negative "could not be loaded" errors even when the SDK
  finished loading correctly.

### Removed

- The misleading `php bin/console assets:install` step from the
  README installation guide. The plugin ships no files under
  `Resources/public/`, so the command is a strict no-op for it; an
  explicit note replaces it.
- `platform: linux/amd64` pin from the development `compose.yml`. Lets
  Docker pick the host architecture so Apple Silicon machines stop
  emulating x86_64 for the MySQL container.

## [1.0.1] - 2026-05-05

Stability release covering integration regressions surfaced on Sylius 2.2,
fresh-install friction, and admin form crashes when used alongside other
payment plugins.

### Fixed

- Container compilation failure on the very first `composer require` —
  the bundle now self-registers the dedicated `hipay` Monolog channel via
  `prependExtensionConfig()`, so `cache:clear` does not blow up with
  *"non-existent service `monolog.logger.hipay`"* before the user has had
  a chance to import the plugin's config.
- Admin "create payment method" page crashing with *"A factory name is
  required."* (Sylius core) or *"Call to a member function
  `getFactoryName()` on null"* (sylius/paypal-plugin's
  `PaymentMethodTypeExtension`) when a brand-new HiPay payment method is
  rendered. The Live Component now ensures a `GatewayConfig` is attached
  with the factory name read from the resource, the dehydrated LiveProp,
  or the `factory` route attribute, before the form is built.
- Checkout select-payment page crashing with *"The 'channels' property on
  component HostedFieldsComponent is missing its property-type. Add the
  Doctrine\\ORM\\PersistentCollection type"* on Sylius 2.x. The
  `paymentMethod` and `payment` LiveProps are now serialized by ID and
  reloaded from the EntityManager on hydration, side-stepping the
  recursive entity-traversal performed by the LiveComponent default.
- Hosted Fields SDK no longer fails to mount with
  *"HIPAY_SELECTOR_MUST_BE_EMPTY"* and the misleading *"This payment
  method could not be loaded (timeout)"* banner on Sylius 2.2. The fix
  layers `data-live-ignore` on each placeholder template (card, PayPal,
  Apple Pay) so morphdom preserves the SDK iframes across LiveComponent
  re-renders, plus a synchronous `data-hipay-mounted` flag and a
  module-level `WeakMap` to share the live SDK instance across the
  multiple Stimulus controllers that Sylius 2.2's UX bump now spawns
  on re-render.
- Pay-button click no longer surfaces *"Cannot read properties of null
  (reading 'getPaymentData')"* when the click is routed to a sibling
  controller that did not own the SDK instance. `submitPayment()` now
  resolves the live instance lazily from the shared map.

### Changed

- npm package renamed from `@hipay/sylius-hipay-plugin` to
  `@hipay/hipay-payments-sylius` to match the published Composer name
  (`hipay/hipay-payments-sylius`). The previous name caused
  `yarn install` to silently fail to link the package, and Stimulus
  Bridge to error with *"file `@hipay/...package.json` could not be
  found"* during `yarn encore dev`.
- README chapter "Install front-end assets" rewritten to walk integrators
  through the actual flow: declaring the npm package as a `file:`
  devDependency in their `package.json`, running `yarn install`, then
  enabling the controllers in `assets/controllers.json` before the
  build. The previous wording skipped the devDependency step entirely.
- HiPay SDK ready timeout raised from 3 s to 30 s. Three seconds was
  shorter than the cold-start cost of `sdkjs.js` plus its sub-chunks
  plus the iframe negotiation with `*.hipay-tpp.com`, which surfaced
  false-negative "could not be loaded" errors even when the SDK
  finished loading correctly.

### Removed

- The misleading `php bin/console assets:install` step from the
  README installation guide. The plugin ships no files under
  `Resources/public/`, so the command is a strict no-op for it; an
  explicit note replaces it.
- `platform: linux/amd64` pin from the development `compose.yml`. Lets
  Docker pick the host architecture so Apple Silicon machines stop
  emulating x86_64 for the MySQL container.

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
  `@hipay/hipay-payments-sylius`, with two Stimulus controllers
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