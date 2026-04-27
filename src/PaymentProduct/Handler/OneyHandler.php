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

use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\GeneralConfigurationType;
use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\OneyCreditLongConfigurationType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\PaymentFallbackDefaultsInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PluginPaymentProduct;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

/**
 * Single handler for all Oney HiPay products; the actual product code comes from gateway
 * configuration ({@see PaymentMethodInterface} gateway config key {@code payment_product}).
 */
final class OneyHandler extends AbstractPaymentProductHandler implements PaymentProductHandlerInterface
{
    protected string $code = 'oney';

    protected string $name = 'sylius_hipay_plugin.payment_product.oney';

    protected ?string $paymentProduct = null;

    public function supports(string $paymentProduct): bool
    {
        // TODO remove return false; and phpstan-ignore-next-line to activate Oney feature
        return false;
        // @phpstan-ignore-next-line
        $this->paymentProduct = $paymentProduct;

        return match ($paymentProduct) {
            PluginPaymentProduct::ONEY_3X->value,
            PluginPaymentProduct::ONEY_3X_NO_FEES->value,
            PluginPaymentProduct::ONEY_4X->value,
            PluginPaymentProduct::ONEY_4X_NO_FEES->value,
            PluginPaymentProduct::ONEY_CREDIT_LONG->value => true,
            default => false,
        };
    }

    public function getJsInitConfig(PaymentMethodInterface $paymentMethod, ?PaymentInterface $payment = null): array
    {
        unset($paymentMethod);

        $request = [];

        if (null !== $payment) {
            $amount = $payment->getAmount();
            $order = $payment->getOrder();
            $request = [
                'amount' => null !== $amount ? (string) ($amount / 100) : '0',
                'currency' => (string) ($order?->getCurrencyCode() ?? PaymentFallbackDefaultsInterface::CURRENCY_CODE),
            ];
        }

        return [
            'template' => 'auto',
            'fields' => [],
            'styles' => [],
            'request' => $request,
        ];
    }

    public function getFormType(): string
    {
        return PluginPaymentProduct::ONEY_CREDIT_LONG->value !== $this->paymentProduct ? GeneralConfigurationType::class : OneyCreditLongConfigurationType::class;
    }

    public function getAvailableCountries(): array
    {
        $countries = ['FR', 'IT', 'PT', 'ES'];
        if (PluginPaymentProduct::ONEY_CREDIT_LONG->value !== $this->paymentProduct) {
            $countries[] = 'BE';
        }

        return $countries;
    }

    /**
     * @return string[]
     */
    public function getAvailableCurrencies(): array
    {
        return ['EUR'];
    }
}
