<?php

/*
 * HiPay payment integration for Sylius
 *
 * (c) Hipay
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use HiPay\SyliusHiPayPlugin\Client\ClientProvider;
use HiPay\SyliusHiPayPlugin\Client\ClientProviderInterface;
use HiPay\SyliusHiPayPlugin\CommandHandler\HostedFieldsPaymentRequestHandler;
use HiPay\SyliusHiPayPlugin\CommandHandler\HostedPagePaymentRequestHandler;
use HiPay\SyliusHiPayPlugin\CommandHandler\NewOrderRequestHandler;
use HiPay\SyliusHiPayPlugin\CommandHandler\TransactionInformationRequestHandler;
use HiPay\SyliusHiPayPlugin\CommandProvider\HostedFieldsPaymentRequestCommandProvider;
use HiPay\SyliusHiPayPlugin\CommandProvider\HostedPagePaymentRequestCommandProvider;
use HiPay\SyliusHiPayPlugin\Controller\Admin\CapturePaymentAction;
use HiPay\SyliusHiPayPlugin\Controller\Shop\AwaitingPaymentController;
use HiPay\SyliusHiPayPlugin\Controller\Shop\CancelPaymentAction;
use HiPay\SyliusHiPayPlugin\Entity\PendingNotification;
use HiPay\SyliusHiPayPlugin\Event\RequestCaptureEvent;
use HiPay\SyliusHiPayPlugin\EventListener\Admin\MenuListener;
use HiPay\SyliusHiPayPlugin\EventListener\RequestCaptureListener;
use HiPay\SyliusHiPayPlugin\EventListener\Workflow\Order\CancelPaymentListenerDecorator;
use HiPay\SyliusHiPayPlugin\EventSubscriber\Admin\OrderLifecycleSubscriber;
use HiPay\SyliusHiPayPlugin\EventSubscriber\FraudSuspicionWebhookNotificationSubscriber;
use HiPay\SyliusHiPayPlugin\EventSubscriber\MultibancoReferenceAfterPaymentSubscriber;
use HiPay\SyliusHiPayPlugin\EventSubscriber\MultibancoReferenceWebhookNotificationSubscriber;
use HiPay\SyliusHiPayPlugin\EventSubscriber\PaymentProductWebhookNotificationSubscriber;
use HiPay\SyliusHiPayPlugin\EventSubscriber\RefundWebhookNotificationSubscriber;
use HiPay\SyliusHiPayPlugin\EventSubscriber\SavedCardWebhookNotificationSubscriber;
use HiPay\SyliusHiPayPlugin\EventSubscriber\Shop\OrphanPaymentCleanupSubscriber;
use HiPay\SyliusHiPayPlugin\Factory\PendingNotificationFactory;
use HiPay\SyliusHiPayPlugin\Factory\PendingNotificationFactoryInterface;
use HiPay\SyliusHiPayPlugin\Factory\SavedCardFactory;
use HiPay\SyliusHiPayPlugin\Factory\SavedCardFactoryInterface;
use HiPay\SyliusHiPayPlugin\Fixture\AccountFixture;
use HiPay\SyliusHiPayPlugin\Fixture\Factory\AccountExampleFactory;
use HiPay\SyliusHiPayPlugin\Fixture\Factory\OneyCategoryExampleFactory;
use HiPay\SyliusHiPayPlugin\Fixture\Factory\OneyShippingMethodExampleFactory;
use HiPay\SyliusHiPayPlugin\Fixture\OneyCategoryFixture;
use HiPay\SyliusHiPayPlugin\Fixture\OneyShippingMethodFixture;
use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\CaptureModeChoiceType;
use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\HostedFieldsConfigurationType;
use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\HostedPageConfigurationType;
use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\HostedPageProductConfigurationType;
use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\HostedPageSettingsType;
use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\AlmaConfigurationType;
use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\GeneralConfigurationType;
use HiPay\SyliusHiPayPlugin\Form\Type\Resource\AccountType;
use HiPay\SyliusHiPayPlugin\Form\Type\Resource\OneyCategoryType;
use HiPay\SyliusHiPayPlugin\Form\Type\Resource\OneyShippingMethodType;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLogger;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Mailer\FraudSuspicionEmailManager;
use HiPay\SyliusHiPayPlugin\Mailer\FraudSuspicionEmailManagerInterface;
use HiPay\SyliusHiPayPlugin\Mailer\MultibancoReferenceEmailManager;
use HiPay\SyliusHiPayPlugin\Mailer\MultibancoReferenceEmailManagerInterface;
use HiPay\SyliusHiPayPlugin\OrderPay\Handler\HiPayPaymentStateFlashHandlerDecorator;
use HiPay\SyliusHiPayPlugin\OrderPay\Provider\HostedFieldsHttpResponseProvider;
use HiPay\SyliusHiPayPlugin\OrderPay\Provider\HostedPageHttpResponseProvider;
use HiPay\SyliusHiPayPlugin\Payment\Capture\PaymentCaptureEligibilityChecker;
use HiPay\SyliusHiPayPlugin\Payment\Capture\PaymentCaptureEligibilityCheckerInterface;
use HiPay\SyliusHiPayPlugin\Payment\Capture\PaymentCaptureHandler;
use HiPay\SyliusHiPayPlugin\Payment\Capture\PaymentCaptureHandlerInterface;
use HiPay\SyliusHiPayPlugin\Payment\Capture\Request\CaptureRequestBuilder;
use HiPay\SyliusHiPayPlugin\Payment\Capture\Request\CaptureRequestBuilderInterface;
use HiPay\SyliusHiPayPlugin\Payment\Capture\Request\Processor\CaptureRequestOperationProcessor;
use HiPay\SyliusHiPayPlugin\Payment\OrderAdvisoryLock;
use HiPay\SyliusHiPayPlugin\Payment\OrderAdvisoryLockInterface;
use HiPay\SyliusHiPayPlugin\Payment\OrphanPaymentCanceller;
use HiPay\SyliusHiPayPlugin\Payment\OrphanPaymentCancellerInterface;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\PaymentEligibilityValidatorRegistry;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\PaymentEligibilityValidatorRegistryInterface;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\OneyBillingPhoneValidator;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\OneyBillingZipCodeValidator;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\OneyCategoryValidator;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\OneyShippingMethodValidator;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\OneyShippingPhoneValidator;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\OneyShippingZipCodeValidator;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\PaypalAmountValidator;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\PaypalCurrencyValidator;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\PaypalShippingAddressValidator;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\HostedPaymentPageRequestBuilder;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\HostedPaymentPageRequestBuilderInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\HostedPaymentPageResponseMapper;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestBuilder;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestBuilderRegistry;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestBuilderRegistryInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContextFactory;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContextFactoryInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\AlmaPaymentMethodProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\ApplePayCustomDataProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\ApplePayPaymentMethodProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\BancomatPayPaymentMethodProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\BancontactPaymentMethodProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\BasketProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\BizumPaymentMethodProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\BrowserInfoProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\CallbackUrlsProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\CaptureModeProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\CardPaymentMethodProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\CommonFieldsProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\CustomerBillingInfoProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\CustomerShippingInfoProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\HostedPageProductsProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\HostedPageSettingsProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\IdealPaymentMethodProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\MbWayPaymentMethodProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\MultibancoPaymentMethodProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\OneyPaymentMethodProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\PaypalPaymentMethodProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\Przelewy24PaymentMethodProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\AlmaHandler;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\ApplePayHandler;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\BancomatPayHandler;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\BancontactHandler;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\BizumHandler;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\CardHandler;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\IdealHandler;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\MbWayHandler;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\MultibancoHandler;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\OneyHandler;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\PaypalHandler;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\Przelewy24Handler;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerRegistry;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerRegistryInterface;
use HiPay\SyliusHiPayPlugin\Processor\CancelProcessor;
use HiPay\SyliusHiPayPlugin\Processor\CancelProcessorInterface;
use HiPay\SyliusHiPayPlugin\Provider\AccountProvider;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\AlmaLimitsProvider;
use HiPay\SyliusHiPayPlugin\Provider\AlmaLimitsProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\CheckoutJsSdkConfigFactory;
use HiPay\SyliusHiPayPlugin\Provider\OneyCategoryProvider;
use HiPay\SyliusHiPayPlugin\Provider\OneyCategoryProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\OneyShippingMethodProvider;
use HiPay\SyliusHiPayPlugin\Provider\OneyShippingMethodProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\PaymentProductProvider;
use HiPay\SyliusHiPayPlugin\Provider\PaymentProductProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\PaymentRequestProvider;
use HiPay\SyliusHiPayPlugin\Provider\PaymentRequestProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\TransactionProvider;
use HiPay\SyliusHiPayPlugin\Provider\TransactionProviderInterface;
use HiPay\SyliusHiPayPlugin\RefundPlugin\Handler\RefundPaymentGeneratedHandler;
use HiPay\SyliusHiPayPlugin\RefundPlugin\StateResolver\OrderFullyRefundedStateResolver;
use HiPay\SyliusHiPayPlugin\RefundPlugin\StateResolver\OrderPartiallyRefundedStateResolver;
use HiPay\SyliusHiPayPlugin\Repository\OneyCategoryRepositoryInterface;
use HiPay\SyliusHiPayPlugin\Repository\OneyShippingMethodRepositoryInterface;
use HiPay\SyliusHiPayPlugin\Repository\PendingNotificationRepository;
use HiPay\SyliusHiPayPlugin\Repository\SavedCardRepositoryInterface;
use HiPay\SyliusHiPayPlugin\Request\Processor\SourceProcessor;
use HiPay\SyliusHiPayPlugin\Resolver\PaymentMethodsResolver;
use HiPay\SyliusHiPayPlugin\Twig\Component\Admin\PaymentMethodFormComponent;
use HiPay\SyliusHiPayPlugin\Twig\Component\Shop\HostedFieldsComponent;
use HiPay\SyliusHiPayPlugin\Twig\Component\Shop\OrderShowPaymentFormComponent;
use HiPay\SyliusHiPayPlugin\Twig\Extension\HipayExtension;
use HiPay\SyliusHiPayPlugin\Twig\Extension\OrderExtension;
use HiPay\SyliusHiPayPlugin\Twig\Extension\PaymentCaptureExtension;
use HiPay\SyliusHiPayPlugin\Validator\HmacValidator;
use HiPay\SyliusHiPayPlugin\Validator\HmacValidatorInterface;
use HiPay\SyliusHiPayPlugin\Validator\UniqueHostedPagePerChannelValidator;
use HiPay\SyliusHiPayPlugin\Webhook\Consumer;
use HiPay\SyliusHiPayPlugin\Webhook\NotificationProcessor;
use HiPay\SyliusHiPayPlugin\Webhook\NotificationProcessorInterface;
use HiPay\SyliusHiPayPlugin\Webhook\RequestParser;
use HiPay\SyliusHiPayPlugin\Webhook\Scheduler\HiPayNotificationsSchedule;
use HiPay\SyliusHiPayPlugin\Webhook\Scheduler\ProcessPendingBatchHandler;
use Sylius\Bundle\AdminBundle\Form\Type\PaymentMethodType;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\SelectPaymentType;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Sylius\RefundPlugin\Checker\OrderFullyRefundedTotalCheckerInterface;
use Sylius\RefundPlugin\Entity\RefundPayment;
use Sylius\RefundPlugin\Event\RefundPaymentGenerated;
use Sylius\RefundPlugin\StateResolver\OrderFullyRefundedStateResolver as BaseOrderFullyRefundedStateResolver;
use Sylius\RefundPlugin\StateResolver\OrderPartiallyRefundedStateResolver as BaseOrderPartiallyRefundedStateResolver;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\DoctrineDbalStore;

return static function (ContainerConfigurator $container): void {
    /*
     * -------------------------------------------------------------------------
     * Plugin parameters (overridable by the host app)
     * -------------------------------------------------------------------------
     * Setting the same parameter in the host app's config (e.g. config/services.yaml)
     * overrides the default — the plugin works out of the box without any extra config.
     *
     * Webhook / scheduler parameters:
     *   buffer_seconds                    — how long Consumer delays a notification
     *                                       before the worker is allowed to claim it.
     *                                       Absorbs the checkout vs. HiPay-notify race.
     *   scheduler.interval_seconds        — tick cadence for the worker to check for
     *                                       eligible rows.
     *   scheduler.batch_size              — max number of rows claimed per tick.
     *   scheduler.max_attempts            — hard cap before a row is marked FAILED.
     *   scheduler.retry_base_delay_seconds / retry_max_delay_seconds
     *                                     — exponential backoff envelope for transient
     *                                       errors (e.g. payment row not yet persisted).
     *   scheduler.stalled_claim_timeout_seconds
     *                                     — grace window after which a PROCESSING row
     *                                       whose worker died is reclaimable.
     *
     * Mobile awaiting-payment page parameters:
     *   awaiting_payment.poll_interval_seconds — JS polling cadence (seconds).
     *   awaiting_payment.expiration_seconds    — after this delay the page shows an
     *                                            expiry message and stops polling.
     */
    $parameters = $container->parameters();
    $parameters->set('sylius_hipay_plugin.awaiting_payment.poll_interval_seconds', 5);
    $parameters->set('sylius_hipay_plugin.awaiting_payment.expiration_seconds', 1800);
    $parameters->set('sylius_hipay_plugin.webhook.buffer_seconds', 180);
    $parameters->set('sylius_hipay_plugin.webhook.scheduler.interval_seconds', 30);
    $parameters->set('sylius_hipay_plugin.webhook.scheduler.batch_size', 50);
    $parameters->set('sylius_hipay_plugin.webhook.scheduler.max_attempts', 8);
    $parameters->set('sylius_hipay_plugin.webhook.scheduler.retry_base_delay_seconds', 30);
    $parameters->set('sylius_hipay_plugin.webhook.scheduler.retry_max_delay_seconds', 3600);
    $parameters->set('sylius_hipay_plugin.webhook.scheduler.stalled_claim_timeout_seconds', 600);

    $services = $container->services();

    $services->defaults()->private();

    /*
     * -------------------------------------------------------------------------
     * Repositories (interfaces)
     * -------------------------------------------------------------------------
     * Allow autowiring repositories by interface while keeping Sylius Resource
     * repository services as the actual implementations.
     */
    $services->alias(SavedCardRepositoryInterface::class, 'sylius.repository.sylius_hipay_plugin.saved_card');
    $services->alias(OneyShippingMethodRepositoryInterface::class, 'sylius.repository.sylius_hipay_plugin.oney_shipping_method');
    $services->alias(OneyCategoryRepositoryInterface::class, 'sylius.repository.sylius_hipay_plugin.oney_category');

    /*
     * -------------------------------------------------------------------------
     * Logging
     * -------------------------------------------------------------------------
     * Structured logging for HiPay flows (uses dedicated monolog channel + fallback).
     */
    $services->set('sylius_hipay_plugin.logger.hipay', HiPayLogger::class)
        ->args([
            service('monolog.logger.hipay'),
            service('logger'),
        ]);
    $services->alias(HiPayLoggerInterface::class, 'sylius_hipay_plugin.logger.hipay');

    /*
     * -------------------------------------------------------------------------
     * HiPay API client
     * -------------------------------------------------------------------------
     * Resolves HiPay SDK clients from configured Account entities.
     */
    $services->set('sylius_hipay_plugin.client.provider', ClientProvider::class)
        ->args([service('sylius_hipay_plugin.repository.account')]);
    $services->alias(ClientProviderInterface::class, 'sylius_hipay_plugin.client.provider');

    /*
     * -------------------------------------------------------------------------
     * Domain providers
     * -------------------------------------------------------------------------
     * Read-side services for accounts, transactions, payment products, and Sylius PaymentRequest creation.
     */
    $services->set('sylius_hipay_plugin.provider.transaction', TransactionProvider::class)
        ->args([service('sylius_hipay_plugin.repository.transaction')]);
    $services->alias(TransactionProviderInterface::class, 'sylius_hipay_plugin.provider.transaction');

    $services->set('sylius_hipay_plugin.provider.oney_category', OneyCategoryProvider::class)
        ->args([service('sylius_hipay_plugin.repository.oney_category')]);
    $services->alias(OneyCategoryProviderInterface::class, 'sylius_hipay_plugin.provider.oney_category');

    $services->set('sylius_hipay_plugin.provider.oney_shipping_method', OneyShippingMethodProvider::class)
        ->args([service('sylius_hipay_plugin.repository.oney_shipping_method')]);
    $services->alias(OneyShippingMethodProviderInterface::class, 'sylius_hipay_plugin.provider.oney_shipping_method');

    $services->set('sylius_hipay_plugin.provider.account', AccountProvider::class)
        ->args([
            service('sylius_hipay_plugin.repository.account'),
        ]);
    $services->alias(AccountProviderInterface::class, 'sylius_hipay_plugin.provider.account');

    $services->set('sylius_hipay_plugin.provider.payment_product', PaymentProductProvider::class)
        ->args([
            service('sylius_hipay_plugin.client.provider'),
            service('translator'),
        ]);
    $services->alias(PaymentProductProviderInterface::class, 'sylius_hipay_plugin.provider.payment_product');

    $services->set('sylius_hipay_plugin.provider.payment_request', PaymentRequestProvider::class)
        ->args([
            service('sylius_abstraction.state_machine'),
            service('sylius.factory.payment_request'),
        ]);
    $services->alias(PaymentRequestProviderInterface::class, 'sylius_hipay_plugin.provider.payment_request');

    /*
     * -------------------------------------------------------------------------
     * Manual capture
     * -------------------------------------------------------------------------
     */
    $services->set('sylius_hipay_plugin.payment.capture_eligibility_checker', PaymentCaptureEligibilityChecker::class)
        ->args([service('sylius.repository.payment_request')]);
    $services->alias(PaymentCaptureEligibilityCheckerInterface::class, 'sylius_hipay_plugin.payment.capture_eligibility_checker');

    $services->set('sylius_hipay_plugin.payment.capture_handler', PaymentCaptureHandler::class)
        ->args([
            service('sylius_hipay_plugin.payment.capture_eligibility_checker'),
            service('sylius_hipay_plugin.client.provider'),
            service('sylius_hipay_plugin.provider.payment_request'),
            service('sylius_hipay_plugin.provider.transaction'),
            service('sylius_hipay_plugin.payment.capture_request_builder'),
            service('sylius_hipay_plugin.payment.order_advisory_lock'),
            service('doctrine.orm.entity_manager'),
        ]);
    $services->alias(PaymentCaptureHandlerInterface::class, 'sylius_hipay_plugin.payment.capture_handler');

    $services->set('sylius_hipay_plugin.event_listener.request_capture', RequestCaptureListener::class)
        ->args([
            service('sylius.repository.payment'),
            service('sylius_hipay_plugin.payment.capture_handler'),
        ])
        ->tag('kernel.event_listener', [
            'event' => RequestCaptureEvent::class,
            'method' => '__invoke',
        ]);

    $services->set('sylius_hipay_plugin.payment.capture_request.processor.operation', CaptureRequestOperationProcessor::class)
        ->tag('sylius_hipay_plugin.capture_request_processor', ['priority' => 30]);

    $services->set('sylius_hipay_plugin.payment.capture_request_builder', CaptureRequestBuilder::class)
        ->args([
            tagged_iterator('sylius_hipay_plugin.capture_request_processor', defaultPriorityMethod: '__none__'),
        ]);
    $services->alias(CaptureRequestBuilderInterface::class, 'sylius_hipay_plugin.payment.capture_request_builder');

    $services->set('sylius_hipay_plugin.twig.payment_capture_extension', PaymentCaptureExtension::class)
        ->args([service('sylius_hipay_plugin.payment.capture_eligibility_checker')])
        ->tag('twig.extension');

    $services->set('sylius_hipay_plugin.controller.shop.cancel_payment', CancelPaymentAction::class)
        ->args([
            service('sylius.repository.order'),
            service('sylius_abstraction.state_machine'),
            service('doctrine.orm.entity_manager'),
            service('router'),
            service('request_stack'),
            service('security.token_storage'),
        ])
        ->tag('controller.service_arguments');

    $services->set('sylius_hipay_plugin.controller.admin.capture_payment', CapturePaymentAction::class)
        ->args([
            service('event_dispatcher'),
            service('router'),
            service('security.csrf.token_manager'),
        ])
        ->tag('controller.service_arguments');

    /*
     * -------------------------------------------------------------------------
     * Validators
     * -------------------------------------------------------------------------
     * Webhook HMAC verification against the matching HiPay account credentials.
     */
    $services->set('sylius_hipay_plugin.validator.hmac', HmacValidator::class)
        ->args([
            service('sylius_hipay_plugin.provider.account'),
            service('sylius_hipay_plugin.logger.hipay'),
        ]);
    $services->alias(HmacValidatorInterface::class, 'sylius_hipay_plugin.validator.hmac');

    $services->set('sylius_hipay_plugin.validator.unique_hosted_page_per_channel', UniqueHostedPagePerChannelValidator::class)
        ->args([service('doctrine.orm.entity_manager')])
        ->tag('validator.constraint_validator', ['alias' => UniqueHostedPagePerChannelValidator::class]);

    $services->set('sylius_hipay_plugin.payment_eligibility.validator_registry', PaymentEligibilityValidatorRegistry::class)
        ->args([
            tagged_locator('sylius_hipay_plugin.payment_eligibility_validator'),
        ]);
    $services->alias(PaymentEligibilityValidatorRegistryInterface::class, 'sylius_hipay_plugin.payment_eligibility.validator_registry');

    $services->set('sylius_hipay_plugin.oney.shipping_zipcode_validator', OneyShippingZipCodeValidator::class)
        ->tag('sylius_hipay_plugin.payment_eligibility_validator');

    $services->set('sylius_hipay_plugin.oney.billing_zipcode_validator', OneyBillingZipCodeValidator::class)
        ->tag('sylius_hipay_plugin.payment_eligibility_validator');

    $services->set('sylius_hipay_plugin.oney.shipping_phone_validator', OneyShippingPhoneValidator::class)
        ->tag('sylius_hipay_plugin.payment_eligibility_validator');

    $services->set('sylius_hipay_plugin.oney.billing_phone_validator', OneyBillingPhoneValidator::class)
        ->tag('sylius_hipay_plugin.payment_eligibility_validator');

    $services->set('sylius_hipay_plugin.oney.oney_category_validator', OneyCategoryValidator::class)
        ->args([
            service('sylius_hipay_plugin.provider.oney_category'),
        ])
        ->tag('sylius_hipay_plugin.payment_eligibility_validator');

    $services->set('sylius_hipay_plugin.oney.oney_shipping_method', OneyShippingMethodValidator::class)
        ->args([
            service('sylius_hipay_plugin.provider.oney_shipping_method'),
        ])
        ->tag('sylius_hipay_plugin.payment_eligibility_validator');

    $services->set('sylius_hipay_plugin.paypal.amount_validator', PaypalAmountValidator::class)
        ->tag('sylius_hipay_plugin.payment_eligibility_validator');

    $services->set('sylius_hipay_plugin.paypal.currency_validator', PaypalCurrencyValidator::class)
        ->tag('sylius_hipay_plugin.payment_eligibility_validator');

    $services->set('sylius_hipay_plugin.paypal.shipping_address_validator', PaypalShippingAddressValidator::class)
        ->tag('sylius_hipay_plugin.payment_eligibility_validator');

    /*
     * -------------------------------------------------------------------------
     * Factories
     * -------------------------------------------------------------------------
     * Saved card factory decoration (Sylius resource factory + plugin behaviour).
     */
    $services->set('sylius_hipay_plugin.custom_factory.saved_card', SavedCardFactory::class)
        ->decorate('sylius_hipay_plugin.factory.saved_card')
        ->args([
            service('sylius_hipay_plugin.custom_factory.saved_card.inner'),
        ]);
    $services->alias(SavedCardFactoryInterface::class, 'sylius_hipay_plugin.custom_factory.saved_card');

    $services->set('sylius_hipay_plugin.factory.pending_notification', PendingNotificationFactory::class);
    $services->alias(PendingNotificationFactoryInterface::class, 'sylius_hipay_plugin.factory.pending_notification');

    /*
     * -------------------------------------------------------------------------
     * Payment product handlers and registry
     * -------------------------------------------------------------------------
     * Handlers are tagged with sylius_hipay_plugin.payment_product_handler and attribute code.
     * Registry: tagged_locator for lazy resolution by code; tagged_iterator for discovery.
     */
    $services->set('sylius_hipay_plugin.payment_product.handler_registry', PaymentProductHandlerRegistry::class)
        ->args([
            tagged_locator('sylius_hipay_plugin.payment_product_handler', 'code'),
            tagged_iterator('sylius_hipay_plugin.payment_product_handler', 'code', 'getCode'),
        ]);
    $services->alias(PaymentProductHandlerRegistryInterface::class, 'sylius_hipay_plugin.payment_product.handler_registry');

    $services->set('sylius_hipay_plugin.payment_product.handler.card', CardHandler::class)
        ->args([
            service(CustomerContextInterface::class),
            service('sylius_hipay_plugin.repository.saved_card'),
            service('clock'),
        ])
        ->tag('sylius_hipay_plugin.payment_product_handler', ['code' => 'card']);

    $services->set('sylius_hipay_plugin.payment_product.handler.ideal', IdealHandler::class)
        ->tag('sylius_hipay_plugin.payment_product_handler', ['code' => 'ideal']);

    $services->set('sylius_hipay_plugin.payment_product.handler.bancontact', BancontactHandler::class)
        ->tag('sylius_hipay_plugin.payment_product_handler', ['code' => 'bancontact']);

    $services->set('sylius_hipay_plugin.payment_product.handler.oney', OneyHandler::class)
        ->tag('sylius_hipay_plugin.payment_product_handler', ['code' => 'oney']);

    $services->set('sylius_hipay_plugin.payment_product.handler.paypal', PaypalHandler::class)
        ->tag('sylius_hipay_plugin.payment_product_handler', ['code' => 'paypal']);

    $services->set('sylius_hipay_plugin.payment_product.handler.apple_pay', ApplePayHandler::class)
        ->tag('sylius_hipay_plugin.payment_product_handler', ['code' => 'apple-pay']);

    $services->set('sylius_hipay_plugin.payment_product.handler.mbway', MbWayHandler::class)
        ->tag('sylius_hipay_plugin.payment_product_handler', ['code' => 'mbway']);

    $services->set('sylius_hipay_plugin.payment_product.handler.multibanco', MultibancoHandler::class)
        ->tag('sylius_hipay_plugin.payment_product_handler', ['code' => 'multibanco']);

    $services->set('sylius_hipay_plugin.payment_product.handler.alma', AlmaHandler::class)
        ->tag('sylius_hipay_plugin.payment_product_handler', ['code' => 'alma']);

    $services->set('sylius_hipay_plugin.payment_product.handler.bancomatpay', BancomatPayHandler::class)
        ->tag('sylius_hipay_plugin.payment_product_handler', ['code' => 'bancomatpay']);

    $services->set('sylius_hipay_plugin.payment_product.handler.bizum', BizumHandler::class)
        ->tag('sylius_hipay_plugin.payment_product_handler', ['code' => 'bizum']);

    $services->set('sylius_hipay_plugin.payment_product.handler.przelewy24', Przelewy24Handler::class)
        ->tag('sylius_hipay_plugin.payment_product_handler', ['code' => 'przelewy24']);

    /*
     * -------------------------------------------------------------------------
     * Payment order request pipelines
     * -------------------------------------------------------------------------
     * Processors enrich the HiPay order request; builder aggregates them per product code.
     * Shared processors are multi-tagged so they run in card, iDEAL, bancontact and paypal pipelines.
     */
    $services->set('sylius_hipay_plugin.payment_order_request.processor.common_fields', CommonFieldsProcessorPayment::class)
        ->args([service('clock')])
        ->tag('sylius_hipay_plugin.order_request_processor.card', ['priority' => 50])
        ->tag('sylius_hipay_plugin.order_request_processor.ideal', ['priority' => 50])
        ->tag('sylius_hipay_plugin.order_request_processor.oney', ['priority' => 50])
        ->tag('sylius_hipay_plugin.order_request_processor.bancontact', ['priority' => 50])
        ->tag('sylius_hipay_plugin.order_request_processor.paypal', ['priority' => 50])
        ->tag('sylius_hipay_plugin.order_request_processor.mbway', ['priority' => 50])
        ->tag('sylius_hipay_plugin.order_request_processor.multibanco', ['priority' => 50])
        ->tag('sylius_hipay_plugin.order_request_processor.apple-pay', ['priority' => 50])
        ->tag('sylius_hipay_plugin.order_request_processor.alma', ['priority' => 50])
        ->tag('sylius_hipay_plugin.order_request_processor.bancomatpay', ['priority' => 50])
        ->tag('sylius_hipay_plugin.order_request_processor.bizum', ['priority' => 50])
        ->tag('sylius_hipay_plugin.order_request_processor.przelewy24', ['priority' => 50])
        ->tag('sylius_hipay_plugin.hosted_page_request_processor', ['priority' => 50]);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.capture_mode', CaptureModeProcessorPayment::class)
        ->tag('sylius_hipay_plugin.order_request_processor.card', ['priority' => 48])
        ->tag('sylius_hipay_plugin.hosted_page_request_processor', ['priority' => 48])
    ;

    // Shared source processor: serves both the order-request and capture pipelines.
    $services->set('sylius_hipay_plugin.request.processor.source', SourceProcessor::class)
        ->tag('sylius_hipay_plugin.order_request_processor.card', ['priority' => 45])
        ->tag('sylius_hipay_plugin.order_request_processor.ideal', ['priority' => 45])
        ->tag('sylius_hipay_plugin.order_request_processor.oney', ['priority' => 45])
        ->tag('sylius_hipay_plugin.order_request_processor.bancontact', ['priority' => 45])
        ->tag('sylius_hipay_plugin.order_request_processor.paypal', ['priority' => 45])
        ->tag('sylius_hipay_plugin.order_request_processor.mbway', ['priority' => 45])
        ->tag('sylius_hipay_plugin.order_request_processor.multibanco', ['priority' => 45])
        ->tag('sylius_hipay_plugin.order_request_processor.apple-pay', ['priority' => 45])
        ->tag('sylius_hipay_plugin.order_request_processor.alma', ['priority' => 45])
        ->tag('sylius_hipay_plugin.order_request_processor.bancomatpay', ['priority' => 45])
        ->tag('sylius_hipay_plugin.order_request_processor.bizum', ['priority' => 45])
        ->tag('sylius_hipay_plugin.order_request_processor.przelewy24', ['priority' => 45])
        ->tag('sylius_hipay_plugin.hosted_page_request_processor', ['priority' => 45])
        ->tag('sylius_hipay_plugin.capture_request_processor', ['priority' => 10]);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.customer_billing', CustomerBillingInfoProcessorPayment::class)
        ->tag('sylius_hipay_plugin.order_request_processor.card', ['priority' => 40])
        ->tag('sylius_hipay_plugin.order_request_processor.ideal', ['priority' => 40])
        ->tag('sylius_hipay_plugin.order_request_processor.bancontact', ['priority' => 40])
        ->tag('sylius_hipay_plugin.order_request_processor.oney', ['priority' => 40])
        ->tag('sylius_hipay_plugin.order_request_processor.mbway', ['priority' => 40])
        ->tag('sylius_hipay_plugin.order_request_processor.multibanco', ['priority' => 40])
        ->tag('sylius_hipay_plugin.order_request_processor.apple-pay', ['priority' => 40])
        ->tag('sylius_hipay_plugin.order_request_processor.alma', ['priority' => 40])
        ->tag('sylius_hipay_plugin.order_request_processor.bancomatpay', ['priority' => 40])
        ->tag('sylius_hipay_plugin.order_request_processor.bizum', ['priority' => 40])
        ->tag('sylius_hipay_plugin.order_request_processor.przelewy24', ['priority' => 40])
        ->tag('sylius_hipay_plugin.hosted_page_request_processor', ['priority' => 40]);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.customer_shipping', CustomerShippingInfoProcessorPayment::class)
        ->tag('sylius_hipay_plugin.order_request_processor.card', ['priority' => 40])
        ->tag('sylius_hipay_plugin.order_request_processor.ideal', ['priority' => 40])
        ->tag('sylius_hipay_plugin.order_request_processor.oney', ['priority' => 40])
        ->tag('sylius_hipay_plugin.order_request_processor.bancontact', ['priority' => 40])
        ->tag('sylius_hipay_plugin.order_request_processor.paypal', ['priority' => 40])
        ->tag('sylius_hipay_plugin.order_request_processor.apple-pay', ['priority' => 40])
        ->tag('sylius_hipay_plugin.order_request_processor.alma', ['priority' => 40])
        ->tag('sylius_hipay_plugin.order_request_processor.przelewy24', ['priority' => 40])
        ->tag('sylius_hipay_plugin.hosted_page_request_processor', ['priority' => 40]);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.basket', BasketProcessorPayment::class)
        ->tag('sylius_hipay_plugin.order_request_processor.card', ['priority' => 35])
        ->tag('sylius_hipay_plugin.order_request_processor.ideal', ['priority' => 35])
        ->tag('sylius_hipay_plugin.order_request_processor.bancontact', ['priority' => 35])
        ->tag('sylius_hipay_plugin.order_request_processor.paypal', ['priority' => 35])
        ->tag('sylius_hipay_plugin.order_request_processor.apple-pay', ['priority' => 35])
        ->tag('sylius_hipay_plugin.order_request_processor.oney', ['priority' => 35])
        ->tag('sylius_hipay_plugin.hosted_page_request_processor', ['priority' => 35])
        ->tag('sylius_hipay_plugin.capture_request_processor', ['priority' => 20]);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.browser_info', BrowserInfoProcessorPayment::class)
        ->args([service('request_stack')])
        ->tag('sylius_hipay_plugin.order_request_processor.card', ['priority' => 30])
        ->tag('sylius_hipay_plugin.order_request_processor.ideal', ['priority' => 30])
        ->tag('sylius_hipay_plugin.order_request_processor.oney', ['priority' => 30])
        ->tag('sylius_hipay_plugin.order_request_processor.bancontact', ['priority' => 30])
        ->tag('sylius_hipay_plugin.order_request_processor.paypal', ['priority' => 30])
        ->tag('sylius_hipay_plugin.order_request_processor.mbway', ['priority' => 30])
        ->tag('sylius_hipay_plugin.order_request_processor.multibanco', ['priority' => 30])
        ->tag('sylius_hipay_plugin.order_request_processor.apple-pay', ['priority' => 30])
        ->tag('sylius_hipay_plugin.order_request_processor.alma', ['priority' => 30])
        ->tag('sylius_hipay_plugin.order_request_processor.bancomatpay', ['priority' => 30])
        ->tag('sylius_hipay_plugin.order_request_processor.bizum', ['priority' => 30])
        ->tag('sylius_hipay_plugin.order_request_processor.przelewy24', ['priority' => 30]);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.callback_urls', CallbackUrlsProcessorPayment::class)
        ->args([service('router')])
        ->tag('sylius_hipay_plugin.order_request_processor.card', ['priority' => 20])
        ->tag('sylius_hipay_plugin.order_request_processor.ideal', ['priority' => 20])
        ->tag('sylius_hipay_plugin.order_request_processor.oney', ['priority' => 20])
        ->tag('sylius_hipay_plugin.order_request_processor.bancontact', ['priority' => 20])
        ->tag('sylius_hipay_plugin.order_request_processor.paypal', ['priority' => 20])
        ->tag('sylius_hipay_plugin.order_request_processor.mbway', ['priority' => 20])
        ->tag('sylius_hipay_plugin.order_request_processor.multibanco', ['priority' => 20])
        ->tag('sylius_hipay_plugin.order_request_processor.apple-pay', ['priority' => 20])
        ->tag('sylius_hipay_plugin.order_request_processor.alma', ['priority' => 20])
        ->tag('sylius_hipay_plugin.order_request_processor.bancomatpay', ['priority' => 20])
        ->tag('sylius_hipay_plugin.order_request_processor.bizum', ['priority' => 20])
        ->tag('sylius_hipay_plugin.order_request_processor.przelewy24', ['priority' => 20])
        ->tag('sylius_hipay_plugin.hosted_page_request_processor', ['priority' => 20]);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.hosted_page_settings', HostedPageSettingsProcessorPayment::class)
        ->tag('sylius_hipay_plugin.hosted_page_request_processor', ['priority' => 15]);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.hosted_page_products', HostedPageProductsProcessorPayment::class)
        ->tag('sylius_hipay_plugin.hosted_page_request_processor', ['priority' => 10]);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.card_payment_method', CardPaymentMethodProcessorPayment::class)
        ->tag('sylius_hipay_plugin.order_request_processor.card', ['priority' => 10]);
    $services->set('sylius_hipay_plugin.payment_order_request.order_request.card', PaymentOrderRequestBuilder::class)
        ->args([
            ['card'],
            tagged_iterator('sylius_hipay_plugin.order_request_processor.card', defaultPriorityMethod: '__none__'),
        ])
        ->tag('sylius_hipay_plugin.order_request_builder', ['code' => 'card']);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.ideal_payment_method', IdealPaymentMethodProcessorPayment::class)
        ->tag('sylius_hipay_plugin.order_request_processor.ideal', ['priority' => 10]);
    $services->set('sylius_hipay_plugin.payment_order_request.order_request.ideal', PaymentOrderRequestBuilder::class)
        ->args([
            ['ideal'],
            tagged_iterator('sylius_hipay_plugin.order_request_processor.ideal', defaultPriorityMethod: '__none__'),
        ])
        ->tag('sylius_hipay_plugin.order_request_builder', ['code' => 'ideal']);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.mbway_payment_method', MbWayPaymentMethodProcessorPayment::class)
        ->tag('sylius_hipay_plugin.order_request_processor.mbway', ['priority' => 10]);

    $services->set('sylius_hipay_plugin.payment_order_request.order_request.mbway', PaymentOrderRequestBuilder::class)
        ->args([
            ['mbway'],
            tagged_iterator('sylius_hipay_plugin.order_request_processor.mbway', defaultPriorityMethod: '__none__'),
        ])
        ->tag('sylius_hipay_plugin.order_request_builder', ['code' => 'mbway']);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.multibanco_payment_method', MultibancoPaymentMethodProcessorPayment::class)
        ->tag('sylius_hipay_plugin.order_request_processor.multibanco', ['priority' => 10]);

    $services->set('sylius_hipay_plugin.payment_order_request.order_request.multibanco', PaymentOrderRequestBuilder::class)
        ->args([
            ['multibanco'],
            tagged_iterator('sylius_hipay_plugin.order_request_processor.multibanco', defaultPriorityMethod: '__none__'),
        ])
        ->tag('sylius_hipay_plugin.order_request_builder', ['code' => 'multibanco']);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.bancontact_payment_method', BancontactPaymentMethodProcessorPayment::class)
        ->tag('sylius_hipay_plugin.order_request_processor.bancontact', ['priority' => 10]);
    $services->set('sylius_hipay_plugin.payment_order_request.order_request.bancontact', PaymentOrderRequestBuilder::class)
        ->args([
            ['bancontact'],
            tagged_iterator('sylius_hipay_plugin.order_request_processor.bancontact', defaultPriorityMethod: '__none__'),
        ])
        ->tag('sylius_hipay_plugin.order_request_builder', ['code' => 'bancontact']);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.oney_payment_method', OneyPaymentMethodProcessorPayment::class)
        ->args([
            service('sylius_hipay_plugin.provider.oney_category'),
            service('sylius_hipay_plugin.provider.oney_shipping_method'),
            service('serializer'),
        ])
        ->tag('sylius_hipay_plugin.order_request_processor.oney', ['priority' => 10])
        ->tag('sylius_hipay_plugin.hosted_page_request_processor', ['priority' => 5]);
    $services->set('sylius_hipay_plugin.payment_order_request.order_request.oney', PaymentOrderRequestBuilder::class)
        ->args([
            ['3xcb', '3xcb-no-fees', '4xcb', '4xcb-no-fees', 'credit-long'],
            tagged_iterator('sylius_hipay_plugin.order_request_processor.oney', defaultPriorityMethod: '__none__'),
        ])
        ->tag('sylius_hipay_plugin.order_request_builder', ['code' => 'oney']);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.paypal_payment_method', PaypalPaymentMethodProcessorPayment::class)
        ->args([
            service('serializer'),
        ])
        ->tag('sylius_hipay_plugin.order_request_processor.paypal', ['priority' => 10]);
    $services->set('sylius_hipay_plugin.payment_order_request.order_request.paypal', PaymentOrderRequestBuilder::class)
        ->args([
            ['paypal'],
            tagged_iterator('sylius_hipay_plugin.order_request_processor.paypal', defaultPriorityMethod: '__none__'),
        ])
        ->tag('sylius_hipay_plugin.order_request_builder', ['code' => 'paypal']);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.apple_pay_payment_method', ApplePayPaymentMethodProcessorPayment::class)
        ->tag('sylius_hipay_plugin.order_request_processor.apple-pay', ['priority' => 10]);
    $services->set('sylius_hipay_plugin.payment_order_request.processor.apple_pay_custom_data', ApplePayCustomDataProcessorPayment::class)
        ->args([
            service('serializer'),
        ])
        ->tag('sylius_hipay_plugin.order_request_processor.apple-pay', ['priority' => 5]);
    $services->set('sylius_hipay_plugin.payment_order_request.order_request.apple_pay', PaymentOrderRequestBuilder::class)
        ->args([
            ['apple-pay'],
            tagged_iterator('sylius_hipay_plugin.order_request_processor.apple-pay', defaultPriorityMethod: '__none__'),
        ])
        ->tag('sylius_hipay_plugin.order_request_builder', ['code' => 'apple-pay']);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.alma_payment_method', AlmaPaymentMethodProcessorPayment::class)
        ->tag('sylius_hipay_plugin.order_request_processor.alma', ['priority' => 10]);
    $services->set('sylius_hipay_plugin.payment_order_request.order_request.alma', PaymentOrderRequestBuilder::class)
        ->args([
            ['alma-3x', 'alma-4x'],
            tagged_iterator('sylius_hipay_plugin.order_request_processor.alma', defaultPriorityMethod: '__none__'),
        ])
        ->tag('sylius_hipay_plugin.order_request_builder', ['code' => 'alma']);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.bancomatpay_payment_method', BancomatPayPaymentMethodProcessorPayment::class)
        ->tag('sylius_hipay_plugin.order_request_processor.bancomatpay', ['priority' => 10]);

    $services->set('sylius_hipay_plugin.payment_order_request.order_request.bancomatpay', PaymentOrderRequestBuilder::class)
        ->args([
            ['bancomatpay'],
            tagged_iterator('sylius_hipay_plugin.order_request_processor.bancomatpay', defaultPriorityMethod: '__none__'),
        ])
        ->tag('sylius_hipay_plugin.order_request_builder', ['code' => 'bancomatpay']);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.bizum_payment_method', BizumPaymentMethodProcessorPayment::class)
        ->tag('sylius_hipay_plugin.order_request_processor.bizum', ['priority' => 10]);

    $services->set('sylius_hipay_plugin.payment_order_request.order_request.bizum', PaymentOrderRequestBuilder::class)
        ->args([
            ['bizum'],
            tagged_iterator('sylius_hipay_plugin.order_request_processor.bizum', defaultPriorityMethod: '__none__'),
        ])
        ->tag('sylius_hipay_plugin.order_request_builder', ['code' => 'bizum']);

    $services->set('sylius_hipay_plugin.payment_order_request.processor.przelewy24_payment_method', Przelewy24PaymentMethodProcessorPayment::class)
        ->tag('sylius_hipay_plugin.order_request_processor.przelewy24', ['priority' => 10]);
    $services->set('sylius_hipay_plugin.payment_order_request.order_request.przelewy24', PaymentOrderRequestBuilder::class)
        ->args([
            ['przelewy24'],
            tagged_iterator('sylius_hipay_plugin.order_request_processor.przelewy24', defaultPriorityMethod: '__none__'),
        ])
        ->tag('sylius_hipay_plugin.order_request_builder', ['code' => 'przelewy24']);

    $services->set('sylius_hipay_plugin.payment_order_request.order_request_registry', PaymentOrderRequestBuilderRegistry::class)
        ->args([tagged_iterator('sylius_hipay_plugin.order_request_builder', 'code')]);
    $services->alias(PaymentOrderRequestBuilderRegistryInterface::class, 'sylius_hipay_plugin.payment_order_request.order_request_registry');

    $services->set('sylius_hipay_plugin.payment_order_request.context_factory', PaymentOrderRequestContextFactory::class)
        ->args([service('sylius_hipay_plugin.repository.account')]);
    $services->alias(PaymentOrderRequestContextFactoryInterface::class, 'sylius_hipay_plugin.payment_order_request.context_factory');

    $services->set('sylius_hipay_plugin.hosted_page.request_builder', HostedPaymentPageRequestBuilder::class)
        ->args([
            tagged_iterator('sylius_hipay_plugin.hosted_page_request_processor', defaultPriorityMethod: '__none__'),
        ]);
    $services->alias(HostedPaymentPageRequestBuilderInterface::class, 'sylius_hipay_plugin.hosted_page.request_builder');

    $services->set('sylius_hipay_plugin.hosted_page.response_mapper', HostedPaymentPageResponseMapper::class);

    /*
     * -------------------------------------------------------------------------
     * Processors (cancel, webhook notifications)
     * -------------------------------------------------------------------------
     * Side-effect services for payment cancellation and asynchronous HiPay notifications.
     */
    $services->set('sylius_hipay_plugin.processor.cancel', CancelProcessor::class)
        ->args([
            service('sylius_hipay_plugin.client.provider'),
            service('sylius_hipay_plugin.provider.transaction'),
            service('sylius_hipay_plugin.provider.account'),
            service('sylius_hipay_plugin.logger.hipay'),
            service('doctrine.orm.entity_manager'),
            service('sylius_hipay_plugin.provider.payment_request'),
        ]);
    $services->alias(CancelProcessorInterface::class, 'sylius_hipay_plugin.processor.cancel');

    /*
     * -------------------------------------------------------------------------
     * Orphan payment deduplication (HIPASYLU001-108)
     * -------------------------------------------------------------------------
     * When a HiPay payment fails, the browser redirect and the webhook can
     * race each other — both trigger Sylius's OrderProcessor which creates a
     * new "new" Payment for the retry. This produces duplicate payment forms
     * on the repayment page. The OrphanPaymentCanceller keeps only the most
     * recent "new" HiPay payment and cancels the rest. It is called from:
     *   1. NotificationProcessor (webhook — with pessimistic DB lock)
     *   2. HostedFieldsHttpResponseProvider (after-pay redirect)
     *   3. OrphanPaymentCleanupSubscriber (repayment page render — safety net)
     */
    // Symfony Lock store/factory backing the order advisory lock. The
    // DoctrineDbalStore works on every database Sylius supports (MySQL,
    // MariaDB, PostgreSQL) — unlike the previous MySQL-only GET_LOCK approach.
    //
    // The `lock.store` tag lets doctrine-bundle's built-in lock schema listener
    // add the `lock_keys` table to schema generation (doctrine:schema:create/update,
    // migrations:diff). Combined with Version20260630120000 this guarantees the
    // table always exists before the store uses it — required on PostgreSQL,
    // where lazily creating it inside an open transaction aborts that transaction
    // (SQLSTATE 25P02). See HIPASYLU003-47.
    $services->set('sylius_hipay_plugin.lock.store', DoctrineDbalStore::class)
        ->args([
            service('doctrine.dbal.default_connection'),
        ])
        ->tag('lock.store');

    $services->set('sylius_hipay_plugin.lock.factory', LockFactory::class)
        ->args([
            service('sylius_hipay_plugin.lock.store'),
        ]);

    $services->set('sylius_hipay_plugin.payment.order_advisory_lock', OrderAdvisoryLock::class)
        ->args([
            service('sylius_hipay_plugin.lock.factory'),
            service('sylius_hipay_plugin.logger.hipay'),
        ]);
    $services->alias(OrderAdvisoryLockInterface::class, 'sylius_hipay_plugin.payment.order_advisory_lock');

    $services->set('sylius_hipay_plugin.payment.orphan_canceller', OrphanPaymentCanceller::class)
        ->args([
            service('sylius_abstraction.state_machine'),
            service('doctrine.orm.entity_manager'),
            service('sylius_hipay_plugin.logger.hipay'),
        ]);
    $services->alias(OrphanPaymentCancellerInterface::class, 'sylius_hipay_plugin.payment.orphan_canceller');

    $services->set('sylius_hipay_plugin.event_subscriber.orphan_payment_cleanup', OrphanPaymentCleanupSubscriber::class)
        ->args([
            service('sylius_hipay_plugin.payment.orphan_canceller'),
            service('sylius.repository.order'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set('sylius_hipay_plugin.processor.notification_processor', NotificationProcessor::class)
        ->args([
            service('sylius_abstraction.state_machine'),
            service('doctrine.orm.entity_manager'),
            service('sylius_hipay_plugin.provider.transaction'),
            service('sylius_hipay_plugin.provider.account'),
            service('sylius_hipay_plugin.logger.hipay'),
            service('sylius_hipay_plugin.provider.payment_request'),
            service('event_dispatcher'),
            service('sylius_hipay_plugin.payment.orphan_canceller'),
            service('sylius_hipay_plugin.payment.order_advisory_lock'),
            service('sylius.repository.payment'),
            service('sylius_hipay_plugin.factory.transaction'),
        ]);
    $services->alias(NotificationProcessorInterface::class, 'sylius_hipay_plugin.processor.notification_processor');

    /*
     * -------------------------------------------------------------------------
     * Mailer
     * -------------------------------------------------------------------------
     * Fraud suspicion notifications sent through Sylius email sender.
     */
    $services->set('sylius_hipay_plugin.mailer.fraud_suspicion', FraudSuspicionEmailManager::class)
        ->args([
            service('sylius.email_sender'),
            service('sylius_hipay_plugin.logger.hipay'),
        ]);
    $services->alias(FraudSuspicionEmailManagerInterface::class, 'sylius_hipay_plugin.mailer.fraud_suspicion');

    $services->set('sylius_hipay_plugin.mailer.multibanco_reference', MultibancoReferenceEmailManager::class)
        ->args([
            service('sylius.email_sender'),
            service('sylius_hipay_plugin.logger.hipay'),
        ]);
    $services->alias(MultibancoReferenceEmailManagerInterface::class, 'sylius_hipay_plugin.mailer.multibanco_reference');

    /*
     * -------------------------------------------------------------------------
     * Sylius Payment Request: command providers, HTTP response, message handlers
     * -------------------------------------------------------------------------
     * Hosted Fields gateway integration with sylius.payment_request.command_bus.
     */
    $services->set('sylius_hipay_plugin.command_provider.hipay_hosted_fields', HostedFieldsPaymentRequestCommandProvider::class)
        ->tag('sylius.payment_request.command_provider', ['gateway_factory' => 'hipay_hosted_fields']);

    $services->set('sylius_hipay_plugin.command_provider.hipay_hosted_page', HostedPagePaymentRequestCommandProvider::class)
        ->tag('sylius.payment_request.command_provider', ['gateway_factory' => 'hipay_hosted_page']);

    $services->set('sylius_hipay_plugin.http_response_provider.hipay_hosted_fields', HostedFieldsHttpResponseProvider::class)
        ->args([
            service('router'),
            service('request_stack'),
            service('sylius_hipay_plugin.payment.orphan_canceller'),
            service('sylius_hipay_plugin.payment.order_advisory_lock'),
        ])
        ->tag('sylius.payment_request.provider.http_response', ['gateway_factory' => 'hipay_hosted_fields']);

    $services->set('sylius_hipay_plugin.http_response_provider.hipay_hosted_page', HostedPageHttpResponseProvider::class)
        ->args([
            service('router'),
            service('request_stack'),
            service('sylius_hipay_plugin.payment.orphan_canceller'),
            service('sylius_hipay_plugin.payment.order_advisory_lock'),
        ])
        ->tag('sylius.payment_request.provider.http_response', ['gateway_factory' => 'hipay_hosted_page']);

    // Suppress Sylius's native "info" flash messages for HiPay payments
    // so the plugin can control flash types and avoid duplicates.
    $services->set(HiPayPaymentStateFlashHandlerDecorator::class)
        ->args([service('.inner')])
        ->decorate('sylius_shop.handler.order_pay.payment_state_flash');

    $paymentRequestMessageHandlerArgs = [
        service('sylius.provider.payment_request'),
        service('sylius_abstraction.state_machine'),
        service('sylius_hipay_plugin.client.provider'),
        service('sylius_hipay_plugin.payment_order_request.order_request_registry'),
        service('serializer'),
        service('doctrine.orm.entity_manager'),
        service('sylius_hipay_plugin.factory.transaction'),
        service('sylius_hipay_plugin.payment_order_request.context_factory'),
        service('sylius_hipay_plugin.repository.transaction'),
        service('sylius_hipay_plugin.logger.hipay'),
        service('sylius_hipay_plugin.mailer.fraud_suspicion'),
        service('event_dispatcher'),
    ];

    $services->set('sylius_hipay_plugin.message_handler.new_order', NewOrderRequestHandler::class)
        ->args($paymentRequestMessageHandlerArgs)
        ->tag('messenger.message_handler', ['bus' => 'sylius.payment_request.command_bus']);

    $services->set('sylius_hipay_plugin.message_handler.transaction_information', TransactionInformationRequestHandler::class)
        ->args($paymentRequestMessageHandlerArgs)
        ->tag('messenger.message_handler', ['bus' => 'sylius.payment_request.command_bus']);

    $services->set('sylius_hipay_plugin.message_handler.hosted_fields', HostedFieldsPaymentRequestHandler::class)
        ->tag('messenger.message_handler', ['bus' => 'sylius.payment_request.command_bus']);

    $services->set('sylius_hipay_plugin.message_handler.hosted_page', HostedPagePaymentRequestHandler::class)
        ->args([
            ...$paymentRequestMessageHandlerArgs,
            service('sylius_hipay_plugin.hosted_page.request_builder'),
            service('sylius_hipay_plugin.hosted_page.response_mapper'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.payment_request.command_bus']);

    /*
     * -------------------------------------------------------------------------
     * Webhook (remote events)
     * -------------------------------------------------------------------------
     * The HTTP thread only parses, verifies HMAC and *buffers* the notification
     * into `hipay_pending_notification`. A Symfony Scheduler worker then claims
     * and applies batches to the NotificationProcessor in priority order.
     *
     * This split absorbs the race between HiPay's async notification and the
     * Sylius checkout finalising the payment row, and keeps the webhook HTTP
     * response bounded.
     */
    $services->set('sylius_hipay_plugin.webhook.request_parser', RequestParser::class)
        ->args([
            service('sylius_hipay_plugin.validator.hmac'),
            service('logger'),
        ]);

    $services->set('sylius_hipay_plugin.webhook.consumer', Consumer::class)
        ->args([
            service('doctrine'),
            service('sylius_hipay_plugin.logger.hipay'),
            service('clock'),
            service('sylius_hipay_plugin.factory.pending_notification'),
            service('sylius_hipay_plugin.provider.transaction'),
            service('sylius_hipay_plugin.provider.account'),
            param('sylius_hipay_plugin.webhook.buffer_seconds'),
            service('sylius_hipay_plugin.processor.notification_processor'),
        ])
        ->tag('remote_event.consumer', ['consumer' => 'hipay']);

    /*
     * -------------------------------------------------------------------------
     * Webhook — pending notifications queue (Symfony Scheduler)
     * -------------------------------------------------------------------------
     * PendingNotificationRepository is obtained via EntityManager::getRepository
     * so the mapping metadata resolves the EntityRepository subclass.
     *
     * HiPayNotificationsSchedule exposes the 'hipay_notifications' schedule to
     * AddScheduleMessengerPass, which auto-registers the paired Messenger
     * transport 'scheduler_hipay_notifications' at container compile time.
     * The worker consumes that transport:
     *     bin/console messenger:consume scheduler_hipay_notifications
     *
     * ProcessPendingBatchHandler runs on every tick: it claims a batch of
     * eligible rows and runs each through NotificationProcessor with per-row
     * error isolation and DB-backed exponential backoff.
     */
    $services->set('sylius_hipay_plugin.repository.pending_notification', PendingNotificationRepository::class)
        ->factory([service('doctrine.orm.entity_manager'), 'getRepository'])
        ->args([PendingNotification::class]);

    $services->set('sylius_hipay_plugin.webhook.scheduler.schedule', HiPayNotificationsSchedule::class)
        ->args([param('sylius_hipay_plugin.webhook.scheduler.interval_seconds')])
        ->tag('scheduler.schedule_provider', ['name' => 'hipay_notifications']);

    $services->set('sylius_hipay_plugin.webhook.scheduler.process_pending_batch_handler', ProcessPendingBatchHandler::class)
        ->args([
            service('sylius_hipay_plugin.repository.pending_notification'),
            service('sylius_hipay_plugin.processor.notification_processor'),
            service('doctrine.orm.entity_manager'),
            service('sylius_hipay_plugin.logger.hipay'),
            service('clock'),
            service('sylius_hipay_plugin.provider.transaction'),
            service('sylius_hipay_plugin.provider.account'),
            param('sylius_hipay_plugin.webhook.scheduler.batch_size'),
            param('sylius_hipay_plugin.webhook.scheduler.max_attempts'),
            param('sylius_hipay_plugin.webhook.scheduler.retry_base_delay_seconds'),
            param('sylius_hipay_plugin.webhook.scheduler.retry_max_delay_seconds'),
            param('sylius_hipay_plugin.webhook.scheduler.stalled_claim_timeout_seconds'),
        ])
        ->tag('messenger.message_handler');

    /*
     * -------------------------------------------------------------------------
     * Event subscribers and listeners
     * -------------------------------------------------------------------------
     * Admin UX, order lifecycle hooks, and card-related webhook reactions.
     */
    $services->set('sylius_hipay_plugin.event_listener.admin.menu', MenuListener::class)
        ->tag('kernel.event_listener', [
            'event' => 'sylius.menu.admin.main',
            'method' => '__invoke',
        ]);

    $services->set('sylius_hipay_plugin.event_subscriber.admin.order_lifecycle', OrderLifecycleSubscriber::class)
        ->args([service('sylius_hipay_plugin.processor.cancel')])
        ->tag('kernel.event_subscriber');

    $services->set('sylius_hipay_plugin.event_subscriber.saved_card_webhook', SavedCardWebhookNotificationSubscriber::class)
        ->args([
            service('sylius_hipay_plugin.custom_factory.saved_card'),
            service('sylius_hipay_plugin.logger.hipay'),
            service('sylius_hipay_plugin.provider.account'),
            service('doctrine.orm.entity_manager'),
            service('sylius_hipay_plugin.repository.saved_card'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set('sylius_hipay_plugin.event_subscriber.fraud_suspicion_webhook', FraudSuspicionWebhookNotificationSubscriber::class)
        ->args([
            service('sylius_hipay_plugin.mailer.fraud_suspicion'),
            service('sylius_hipay_plugin.provider.account'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set('sylius_hipay_plugin.event_subscriber.multibanco_reference_webhook_notification', MultibancoReferenceWebhookNotificationSubscriber::class)
        ->args([
            service('sylius_hipay_plugin.mailer.multibanco_reference'),
            service('sylius_hipay_plugin.provider.account'),
            service('sylius_hipay_plugin.logger.hipay'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set('sylius_hipay_plugin.event_subscriber.payment_product_webhook_notification', PaymentProductWebhookNotificationSubscriber::class)
        ->args([
            service('doctrine.orm.entity_manager'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set('sylius_hipay_plugin.event_subscriber.multibanco_reference_after_payment', MultibancoReferenceAfterPaymentSubscriber::class)
        ->args([
            service('sylius_hipay_plugin.mailer.multibanco_reference'),
            service('sylius_hipay_plugin.provider.account'),
            service('serializer'),
        ])
        ->tag('kernel.event_subscriber');

    /*
     * -------------------------------------------------------------------------
     * Form types
     * -------------------------------------------------------------------------
     * Admin Account CRUD and HiPay gateway configuration forms.
     */
    $services->set('sylius_hipay_plugin.form.type.resource_account', AccountType::class)
        ->args([
            param('sylius_hipay_plugin.model.account.class'),
            ['sylius_hipay_plugin'],
        ])
        ->tag('form.type');

    $services->set('sylius_hipay_plugin.form.type.general_configuration', GeneralConfigurationType::class)
        ->args([
            service('sylius.repository.country'),
            service('sylius.repository.currency'),
            service('sylius_hipay_plugin.payment_product.handler_registry'),
        ])
        ->tag('form.type');

    $services->set('sylius_hipay_plugin.provider.alma_limits', AlmaLimitsProvider::class)
        ->args([
            service(ClientProviderInterface::class),
        ]);
    $services->alias(AlmaLimitsProviderInterface::class, 'sylius_hipay_plugin.provider.alma_limits');

    $services->set('sylius_hipay_plugin.form.type.alma_configuration', AlmaConfigurationType::class)
        ->args([
            service('sylius.repository.country'),
            service('sylius.repository.currency'),
            service('sylius_hipay_plugin.payment_product.handler_registry'),
            service(AlmaLimitsProviderInterface::class),
        ])
        ->tag('form.type');

    $services->set('sylius_hipay_plugin.form.type.hosted_fields_gateway_configuration', HostedFieldsConfigurationType::class)
        ->args([
            service('sylius_hipay_plugin.provider.account'),
            service('sylius_hipay_plugin.provider.payment_product'),
            service('sylius_hipay_plugin.payment_product.handler_registry'),
        ])
        ->tag('sylius.gateway_configuration_type', ['type' => 'hipay_hosted_fields', 'label' => 'sylius_hipay_plugin.ui.gateway_configuration_type.hosted_fields'])
        ->tag('form.type');

    $services->set('sylius_hipay_plugin.form.type.capture_mode_choice', CaptureModeChoiceType::class)
        ->tag('form.type');

    $services->set('sylius_hipay_plugin.form.type.hosted_page_settings', HostedPageSettingsType::class)
        ->tag('form.type');

    $services->set('sylius_hipay_plugin.form.type.hosted_page_product_configuration', HostedPageProductConfigurationType::class)
        ->tag('form.type');

    $services->set('sylius_hipay_plugin.form.type.hosted_page_gateway_configuration', HostedPageConfigurationType::class)
        ->args([
            service('sylius_hipay_plugin.provider.account'),
            service('sylius_hipay_plugin.provider.payment_product'),
            service('sylius_hipay_plugin.payment_product.handler_registry'),
        ])
        ->tag('sylius.gateway_configuration_type', ['type' => 'hipay_hosted_page', 'label' => 'sylius_hipay_plugin.ui.gateway_configuration_type.hosted_page'])
        ->tag('form.type');

    $services->set('sylius_hipay_plugin.form.type.resource_oney_category', OneyCategoryType::class)
        ->args([
            param('sylius_hipay_plugin.model.oney_category.class'),
            ['sylius_hipay_plugin'],
            service('sylius.repository.taxon'),
            service('sylius_hipay_plugin.repository.oney_category'),
        ])
        ->tag('form.type');

    $services->set('sylius_hipay_plugin.form.type.resource_oney_shipping_method', OneyShippingMethodType::class)
        ->args([
            param('sylius_hipay_plugin.model.oney_shipping_method.class'),
            ['sylius_hipay_plugin'],
            service('sylius.repository.shipping_method'),
            service('sylius_hipay_plugin.repository.oney_shipping_method'),
        ])
        ->tag('form.type');

    /*
     * -------------------------------------------------------------------------
     * Twig components and extensions
     * -------------------------------------------------------------------------
     * Shop checkout LiveComponent, admin payment method form override, shared Twig helpers.
     */
    $services->set('sylius_hipay_plugin.provider.checkout_js_sdk_config_factory', CheckoutJsSdkConfigFactory::class)
        ->args([
            service('sylius_hipay_plugin.provider.account'),
            service('sylius_hipay_plugin.payment_product.handler_registry'),
            service('event_dispatcher'),
            service('sylius_hipay_plugin.payment_eligibility.validator_registry'),
            service('translator'),
        ]);

    $services->set('sylius_hipay_plugin.twig.hipay_extension', HipayExtension::class)
        ->args([
            service('http_client'),
            service('sylius_hipay_plugin.provider.checkout_js_sdk_config_factory'),
        ])
        ->tag('twig.extension');

    $services->set('sylius_hipay_plugin.twig.order_extension', OrderExtension::class)
        ->args([
            service('sylius.repository.order'),
            service('request_stack'),
            service('serializer'),
        ])
        ->tag('twig.extension');

    $services->set('sylius_hipay_plugin.twig.component.shop.hosted_fields', HostedFieldsComponent::class)
        ->args([
            service('sylius_hipay_plugin.provider.checkout_js_sdk_config_factory'),
            service('doctrine.orm.entity_manager'),
            service('serializer'),
            service('event_dispatcher'),
        ])
        ->call('setLiveResponder', [service('ux.live_component.live_responder')])
        ->tag('sylius.live_component.shop', [
            'key' => 'hipay_hosted_fields',
            'template' => '@SyliusHiPayPlugin/components/hosted_fields.html.twig',
        ]);

    /*
     * Order show (repayment) payment form as a LiveComponent so the HiPay
     * hipay_hosted_fields component (Hosted Fields) works on the repayment page
     * the same way it does during checkout.
     */
    $services->set('sylius_hipay_plugin.twig.component.shop.order_show_payment_form', OrderShowPaymentFormComponent::class)
        ->args([
            service('sylius.repository.order'),
            service('form.factory'),
            param('sylius.model.order.class'),
            SelectPaymentType::class,
        ])
        ->tag('sylius.live_component.shop', [
            'key' => 'hipay_order_show:payment:form',
            'template' => '@SyliusHiPayPlugin/shop/order/show/content/live_form.html.twig',
        ]);

    /*
     * Override Sylius payment method form: dedicated LiveComponent keeps gatewayFactoryName
     * across re-renders so GatewayConfig (Payum, gateway config) is built correctly.
     */
    $services->set('sylius_admin.twig.component.payment_method.form', PaymentMethodFormComponent::class)
        ->args([
            service('sylius.repository.payment_method'),
            service('form.factory'),
            param('sylius.model.payment_method.class'),
            PaymentMethodType::class,
            param('sylius.model.gateway_config.class'),
            service('request_stack'),
        ])
        ->tag('sylius.live_component.admin', [
            'key' => 'sylius_admin:payment_method:form',
        ]);

    /*
     * -------------------------------------------------------------------------
     * Decorators (Sylius core / workflow)
     * -------------------------------------------------------------------------
     * Filters payment methods by channel and augments order cancel-payment workflow behaviour.
     */
    $services->set('sylius_hipay_plugin.resolver.payment_methods.channel_based_decorator', PaymentMethodsResolver::class)
        ->decorate('sylius.resolver.payment_methods.channel_based')
        ->args([service('.inner')]);

    $services->set('sylius_hipay_plugin.listener.workflow.order.cancel_payment_decorator', CancelPaymentListenerDecorator::class)
        ->decorate('sylius.listener.workflow.order.cancel_payment')
        ->args([service('.inner')]);

    /*
     * -------------------------------------------------------------------------
     * Fixtures
     * -------------------------------------------------------------------------
     * Sylius fixtures bundle: sample HiPay Account data.
     */
    $services->set('sylius_hipay_plugin.fixture.factory.account', AccountExampleFactory::class);

    $services->set('sylius_hipay_plugin.fixture.account', AccountFixture::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('sylius_hipay_plugin.fixture.factory.account'),
        ])
        ->tag('sylius_fixtures.fixture');

    $services->set('sylius_hipay_plugin.fixture.factory.oney_category', OneyCategoryExampleFactory::class)
        ->args([
            service('sylius.repository.taxon'),
        ]);

    $services->set('sylius_hipay_plugin.fixture.oney_category', OneyCategoryFixture::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('sylius_hipay_plugin.fixture.factory.oney_category'),
        ])
        ->tag('sylius_fixtures.fixture');

    $services->set('sylius_hipay_plugin.fixture.factory.oney_shipping_method', OneyShippingMethodExampleFactory::class)
        ->args([
            service('sylius.repository.shipping_method'),
        ]);

    $services->set('sylius_hipay_plugin.fixture.oney_shipping_method', OneyShippingMethodFixture::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('sylius_hipay_plugin.fixture.factory.oney_shipping_method'),
        ])
        ->tag('sylius_fixtures.fixture');

    /*
     * -------------------------------------------------------------------------
     * Refund plugin integration (optional)
     * -------------------------------------------------------------------------
     * Decorates RefundPlugin state resolvers, handles refund payment generation events,
     * and completes refund payments from HiPay webhooks when RefundPlugin is present.
     */
    if (class_exists(BaseOrderPartiallyRefundedStateResolver::class)) {
        $services->set('sylius_hipay_plugin.refund.state_resolver.order_partially_refunded', OrderPartiallyRefundedStateResolver::class)
            ->decorate('sylius_refund.state_resolver.order_partially_refunded')
            ->args([
                service('.inner'),
                service('sylius.repository.order'),
            ]);
    }

    if (class_exists(BaseOrderFullyRefundedStateResolver::class)) {
        $services->set('sylius_hipay_plugin.refund.state_resolver.order_fully_refunded', OrderFullyRefundedStateResolver::class)
            ->decorate('sylius_refund.state_resolver.order_fully_refunded')
            ->args([
                service('.inner'),
                service(OrderFullyRefundedTotalCheckerInterface::class),
                service('sylius.repository.order'),
            ]);
    }

    if (class_exists(RefundPaymentGenerated::class)) {
        $services->set('sylius_hipay_plugin.refund.handler.refund_payment_generated', RefundPaymentGeneratedHandler::class)
            ->args([
                service('sylius.repository.order'),
                service('sylius.repository.payment_method'),
                service('sylius_hipay_plugin.client.provider'),
                service('sylius_hipay_plugin.provider.transaction'),
                service('sylius_hipay_plugin.provider.account'),
                service('sylius_hipay_plugin.logger.hipay'),
                service('doctrine.orm.entity_manager'),
                service('sylius_hipay_plugin.provider.payment_request'),
            ])
            ->tag('messenger.message_handler', ['bus' => 'sylius.event_bus', 'handles' => RefundPaymentGenerated::class]);
    }

    $services->set('sylius_hipay_plugin.controller.shop.awaiting_payment', AwaitingPaymentController::class)
        ->args([
            service('twig'),
            service('sylius.repository.order'),
            service('router'),
            service('security.token_storage'),
            param('sylius_hipay_plugin.awaiting_payment.poll_interval_seconds'),
            param('sylius_hipay_plugin.awaiting_payment.expiration_seconds'),
        ])
        ->public();

    if (class_exists(RefundPayment::class)) {
        $services->set('sylius_hipay_plugin.event_subscriber.refund_webhook', RefundWebhookNotificationSubscriber::class)
            ->args([
                service('sylius_refund.repository.refund_payment'),
                service('sylius_refund.state_resolver.refund_payment_completed_applier'),
                service('sylius_hipay_plugin.logger.hipay'),
            ])
            ->tag('kernel.event_subscriber');
    }
};
