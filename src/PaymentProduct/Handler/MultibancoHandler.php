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

use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\MultibancoConfigurationType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

class MultibancoHandler extends AbstractPaymentProductHandler implements PaymentProductHandlerInterface
{
    protected string $code = 'multibanco';

    protected string $name = 'hipay.payment_product.multibanco';

    public function getFormType(): ?string
    {
        return MultibancoConfigurationType::class;
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

    /**
     * @see https://developer.hipay.com/online-payments/payment-means/ideal
     *
     * @return string[]
     */
    public function getAvailableCountries(): array
    {
        return ['PT'];
    }

    /**
     * @see https://developer.hipay.com/online-payments/payment-means/ideal
     *
     * @return string[]
     */
    public function getAvailableCurrencies(): array
    {
        return ['EUR'];
    }
}
