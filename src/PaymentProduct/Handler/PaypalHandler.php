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

use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\PaypalConfigurationType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\PaymentFallbackDefaultsInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\PaypalConfigurationDefaultsInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

/**
 * @see https://developer.hipay.com/online-payments/payment-means/paypal
 */
class PaypalHandler extends AbstractPaymentProductHandler implements PaymentProductHandlerInterface
{
    protected string $code = 'paypal';

    protected string $name = 'hipay.payment_product.paypal';

    public function getFormType(): string
    {
        return PaypalConfigurationType::class;
    }

    public function getJsInitConfig(PaymentMethodInterface $paymentMethod, ?PaymentInterface $payment = null): array
    {
        /** @var array<string, mixed>|null $config */
        $config = $paymentMethod->getGatewayConfig()?->getConfig()['configuration'] ?? null;

        /** @var int $buttonHeight */
        $buttonHeight = $config['button_height'] ?? PaypalConfigurationDefaultsInterface::BUTTON_HEIGHT;

        $paypalButtonStyle = [
            'shape' => $config['button_shape'] ?? PaypalConfigurationDefaultsInterface::BUTTON_SHAPE,
            'color' => $config['button_color'] ?? PaypalConfigurationDefaultsInterface::BUTTON_COLOR,
            'label' => $config['button_label'] ?? PaypalConfigurationDefaultsInterface::BUTTON_LABEL,
            'height' => (int) $buttonHeight,
        ];

        $request = [];
        $customerShippingInformation = [];

        if (null !== $payment) {
            $amount = $payment->getAmount();
            $order = $payment->getOrder();

            $shippingAddress = $order?->getShippingAddress();
            if (null !== $shippingAddress) {
                $customerShippingInformation = [
                    'shippingType' => 'SHIPPING',
                    'zipCode' => (string) $shippingAddress->getPostcode(),
                    'city' => (string) $shippingAddress->getCity(),
                    'country' => (string) $shippingAddress->getCountryCode(),
                    'streetaddress' => (string) $shippingAddress->getStreet(),
                    'firstname' => (string) $shippingAddress->getFirstName(),
                    'lastname' => (string) $shippingAddress->getLastName(),
                ];
            }
            $request = [
                'amount' => null !== $amount ? (string) ($amount / 100) : '0',
                'currency' => (string) ($order?->getCurrencyCode() ?? PaymentFallbackDefaultsInterface::CURRENCY_CODE),
                'customerShippingInformation' => $customerShippingInformation,
            ];
        }

        return [
            'template' => 'auto',
            'canPayLater' => (bool) ($config['can_pay_later'] ?? PaypalConfigurationDefaultsInterface::CAN_PAY_LATER),
            'paypalButtonStyle' => $paypalButtonStyle,
            'request' => $request,
        ];
    }
}
