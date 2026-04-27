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

namespace HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor;

use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\PhonePaymentMethod;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestProcessorInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PluginPaymentProduct;

/**
 * Sets payment_product to "ideal" for iDEAL transactions.
 * iDEAL is a redirect-based method; no card token or 3DS indicator needed.
 */
final class MbWayPaymentMethodProcessorPayment implements PaymentOrderRequestProcessorInterface
{
    public function process(OrderRequest $orderRequest, PaymentOrderRequestContext $context): void
    {
        $orderRequest->payment_product = PluginPaymentProduct::MBWAY->value;

        $method = new PhonePaymentMethod();
        // @phpstan-ignore-next-line
        $method->phone = $context->payload['phone'] ?? '';

        $orderRequest->paymentMethod = $method;
    }
}
