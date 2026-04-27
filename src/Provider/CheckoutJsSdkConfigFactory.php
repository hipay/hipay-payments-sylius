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

namespace HiPay\SyliusHiPayPlugin\Provider;

use HiPay\SyliusHiPayPlugin\Event\CheckoutSdkConfigResolvedEvent;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\PaymentEligibilityValidationResult;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\PaymentEligibilityValidatorInterface;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\PaymentEligibilityValidatorRegistryInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerRegistryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds the same JS SDK payload as {@see HiPayCheckoutComponent::getJsSdkConfig()} for any payment context (checkout LiveComponent, thank-you page, etc.).
 */
final class CheckoutJsSdkConfigFactory
{
    public function __construct(
        private readonly AccountProviderInterface $accountProvider,
        private readonly PaymentProductHandlerRegistryInterface $paymentProductHandlerRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PaymentEligibilityValidatorRegistryInterface $paymentEligibilityValidatorRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function create(?PaymentMethodInterface $paymentMethod, ?PaymentInterface $payment): array
    {
        if (null === $paymentMethod) {
            return [];
        }

        $account = $this->accountProvider->getByPaymentMethod($paymentMethod);
        $paymentProductHandler = $this->paymentProductHandlerRegistry->getForPaymentMethod($paymentMethod);
        if (null === $paymentProductHandler || null === $account) {
            return [];
        }

        $configuration = $paymentProductHandler->getJsInitConfig($paymentMethod, $payment);

        // Allow handler to override the SDK product code (e.g. apple-pay → paymentRequestButton)
        $sdkProductOverride = $configuration['_sdkProduct'] ?? null;
        unset($configuration['_sdkProduct']);

        $browserCheckType = $configuration['_browserCheck'] ?? null;
        unset($configuration['_browserCheck']);

        $gatewayConfig = $paymentMethod->getGatewayConfig()?->getConfig() ?? [];
        $configuredProduct = $gatewayConfig['payment_product'] ?? null;

        $sdkProduct = $paymentProductHandler->getCode();
        if (is_string($configuredProduct) && '' !== $configuredProduct) {
            $sdkProduct = $configuredProduct;
        }
        if (is_string($sdkProductOverride) && '' !== $sdkProductOverride) {
            $sdkProduct = $sdkProductOverride;
        }

        $sdkConfig = [
            'product' => $sdkProduct,
            'username' => $account->getPublicUsernameForCurrentEnv(),
            'password' => $account->getPublicPasswordForCurrentEnv(),
            'environment' => $account->isTestMode() ? 'stage' : 'production',
            'debug' => $account->isDebugMode(),
        'lang' => $this->resolveShortLocale($payment),
            'configuration' => $configuration,
        ];

        $sdkConfig['eligibility'] = $this->buildEligibilityPayload($paymentProductHandler, $payment);

        $sdkConfig['clientMessages'] = [
            'sdkLoadFailed' => $this->translator->trans('sylius_hipay_plugin.checkout.hosted_fields.sdk_load_failed'),
            'paymentProcessingFailed' => $this->translator->trans('sylius_hipay_plugin.checkout.hosted_fields.payment_processing_failed'),
        ];

        if (is_string($browserCheckType) && '' !== $browserCheckType) {
            $sdkConfig['browserCheck'] = [
                'type' => $browserCheckType,
                'message' => $this->translator->trans(
                    'sylius_hipay_plugin.checkout.' . $paymentProductHandler->getCode() . '.browser_not_supported',
                ),
            ];
        }

        $event = new CheckoutSdkConfigResolvedEvent($sdkConfig, $paymentMethod, $payment);
        $this->eventDispatcher->dispatch($event);

        return $event->getSdkConfig();
    }

    /**
     * @return array{blocked: bool, messages?: list<string>}
     */
    public function buildEligibility(?PaymentMethodInterface $paymentMethod, ?PaymentInterface $payment): array
    {
        if (null === $paymentMethod) {
            return ['blocked' => false];
        }

        $paymentProductHandler = $this->paymentProductHandlerRegistry->getForPaymentMethod($paymentMethod);
        if (null === $paymentProductHandler) {
            return ['blocked' => false];
        }

        return $this->buildEligibilityPayload($paymentProductHandler, $payment);
    }

    /**
     * Languages supported by the HiPay JS SDK for Hosted Fields rendering.
     * When the Sylius locale is not in this list, the SDK falls back to its
     * own default ("en"). HiPay requires "fr" as the fallback instead.
     *
     * @see https://developer.hipay.com/online-payments/sdk-reference/sdk-js
     */
    private const SUPPORTED_SDK_LANGUAGES = ['fr', 'en', 'es', 'pt', 'de', 'it', 'nl'];

    /**
     * Resolves a two-letter language code from the order locale (e.g. fr_FR → fr).
     * Falls back to "fr" when the locale is missing or not supported by the
     * HiPay JS SDK, so the SDK never defaults to English on its own.
     */
    private function resolveShortLocale(?PaymentInterface $payment): string
    {
        $locale = $payment?->getOrder()?->getLocaleCode();
        $lang = null !== $locale ? strtolower(substr($locale, 0, 2)) : null;

        return in_array($lang, self::SUPPORTED_SDK_LANGUAGES, true) ? $lang : 'fr';
    }

    /**
     * @return array{blocked: bool, messages?: list<string>}
     */
    private function buildEligibilityPayload(PaymentProductHandlerInterface $handler, ?PaymentInterface $payment): array
    {
        $validators = $this->paymentEligibilityValidatorRegistry->get($handler->getCode());
        if ([] === $validators) {
            return ['blocked' => false];
        }
        $messages = [];
        /** @var PaymentEligibilityValidatorInterface $validator */
        foreach ($validators as $validator) {
            $result = $validator->validate($payment);
            if (!$result instanceof PaymentEligibilityValidationResult) {
                continue;
            }
            $messages[] = $this->translator->trans($result->message, $result->parameters);
        }
        if ([] === $messages) {
            return ['blocked' => false];
        }

        return [
            'blocked' => true,
            'messages' => $messages,
        ];
    }
}
