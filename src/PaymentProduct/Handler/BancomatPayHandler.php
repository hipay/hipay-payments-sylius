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

use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

class BancomatPayHandler extends AbstractPaymentProductHandler implements PaymentProductHandlerInterface
{
    protected string $code = 'bancomatpay';

    protected string $name = 'hipay.payment_product.bancomatpay';

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
     * @see https://developer.hipay.com/online-payments/payment-means/bancomat-pay
     *
     * @return string[]
     */
    public function getAvailableCountries(): array
    {
        return ['IT'];
    }

    /**
     * @see https://developer.hipay.com/online-payments/payment-means/bancomat-pay
     *
     * @return string[]
     */
    public function getAvailableCurrencies(): array
    {
        return ['EUR'];
    }
}
