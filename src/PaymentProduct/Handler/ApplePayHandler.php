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

namespace HiPay\SyliusHiPayPlugin\PaymentProduct\Handler;

use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\ApplePayConfigurationType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\ApplePayConfigurationDefaultsInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\PaymentFallbackDefaultsInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

/**
 * Apple Pay uses the HiPay JS SDK with product type "paymentRequestButton".
 * The gateway config stores "apple-pay" as payment_product for handler resolution;
 * the _sdkProduct key tells the LiveComponent to override the SDK product code.
 *
 * @see https://developer.hipay.com/online-payments/payment-means/apple-pay-web
 */
class ApplePayHandler extends AbstractPaymentProductHandler implements PaymentProductHandlerInterface
{
    protected string $code = 'apple-pay';

    protected string $name = 'hipay.payment_product.apple-pay';

    public function getFormType(): string
    {
        return ApplePayConfigurationType::class;
    }

    public function getJsInitConfig(PaymentMethodInterface $paymentMethod, ?PaymentInterface $payment = null): array
    {
        /** @var array<string, mixed>|null $config */
        $config = $paymentMethod->getGatewayConfig()?->getConfig()['configuration'] ?? null;

        $rawDisplayName = $config['display_name'] ?? '';
        $displayName = is_string($rawDisplayName) ? $rawDisplayName : '';
        if ('' === $displayName && null !== $payment) {
            /** @var OrderInterface|null $order */
            $order = $payment->getOrder();
            $channelName = $order?->getChannel()?->getName();
            $displayName = is_string($channelName) ? $channelName : '';
        }

        /** @var array<int, string> $supportedNetworks */
        $supportedNetworks = $config['supported_networks'] ?? ApplePayConfigurationDefaultsInterface::SUPPORTED_NETWORKS;

        $request = [];
        if (null !== $payment) {
            $amount = $payment->getAmount();
            /** @var OrderInterface|null $order */
            $order = $payment->getOrder();
            $countryCode = $order?->getBillingAddress()?->getCountryCode() ?? PaymentFallbackDefaultsInterface::COUNTRY_CODE;
            $currencyCode = $order?->getCurrencyCode() ?? PaymentFallbackDefaultsInterface::CURRENCY_CODE;

            $request = [
                'total' => [
                    // Use the merchant display name (configured or channel name fallback) as
                    // Apple Pay's total.label — this is what appears in the payment sheet.
                    // See HIPASYLU001-122.
                    'label' => '' !== $displayName ? $displayName : 'Total',
                    'amount' => null !== $amount ? (string) ($amount / 100) : '0',
                ],
                'countryCode' => $countryCode,
                'currencyCode' => $currencyCode,
                'supportedNetworks' => array_values($supportedNetworks),
            ];
        }

        return [
            'template' => 'auto',
            '_sdkProduct' => 'paymentRequestButton',
            '_browserCheck' => 'applePaySession',
            'displayName' => $displayName,
            'applePayStyle' => [
                'type' => is_string($config['button_type'] ?? null) ? $config['button_type'] : ApplePayConfigurationDefaultsInterface::BUTTON_TYPE,
                'color' => is_string($config['button_color'] ?? null) ? $config['button_color'] : ApplePayConfigurationDefaultsInterface::BUTTON_COLOR,
            ],
            'request' => $request,
        ];
    }
}
