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

class Przelewy24Handler extends AbstractPaymentProductHandler implements PaymentProductHandlerInterface
{
    protected string $code = 'przelewy24';

    protected string $name = 'hipay.payment_product.przelewy24';

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
     * @see https://developer.hipay.com/online-payments/payment-means/przelewy-24
     *
     * @return string[]
     */
    public function getAvailableCountries(): array
    {
        return ['PL'];
    }

    /**
     * @see https://developer.hipay.com/online-payments/payment-means/przelewy-24
     *
     * @return string[]
     */
    public function getAvailableCurrencies(): array
    {
        return ['PLN'];
    }
}
