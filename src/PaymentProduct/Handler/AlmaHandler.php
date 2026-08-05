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

use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\AlmaConfigurationType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PluginPaymentProduct;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

/**
 * Single handler for all Alma HiPay products; the actual product code comes from gateway
 * configuration ({@see PaymentMethodInterface} gateway config key {@code payment_product}).
 */
final class AlmaHandler extends AbstractPaymentProductHandler implements PaymentProductHandlerInterface
{
    protected string $code = 'alma';

    protected string $name = 'sylius_hipay_plugin.payment_product.alma';

    public function getFormType(): ?string
    {
        return AlmaConfigurationType::class;
    }

    public function getJsInitConfig(PaymentMethodInterface $paymentMethod, ?PaymentInterface $payment = null): array
    {
        unset($paymentMethod, $payment);

        return [
            'template' => 'auto',
            'fields' => [],
            'styles' => [],
        ];
    }

    public function supports(string $paymentProduct): bool
    {
        return match ($paymentProduct) {
            PluginPaymentProduct::ALMA_3X->value,
            PluginPaymentProduct::ALMA_4X->value => true,
            default => false,
        };
    }

    /**
     * @see https://developer.hipay.com/online-payments/payment-means/alma
     *
     * @return string[]
     */
    public function getAvailableCountries(): array
    {
        return ['FR'];
    }

    /**
     * @see https://developer.hipay.com/online-payments/payment-means/alma
     *
     * @return string[]
     */
    public function getAvailableCurrencies(): array
    {
        return ['EUR'];
    }
}
