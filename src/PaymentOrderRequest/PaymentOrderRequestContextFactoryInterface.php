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

namespace HiPay\SyliusHiPayPlugin\PaymentOrderRequest;

use Sylius\Component\Payment\Model\PaymentRequestInterface;

interface PaymentOrderRequestContextFactoryInterface
{
    public function buildFromPaymentRequest(PaymentRequestInterface $paymentRequest): PaymentOrderRequestContext;
}
