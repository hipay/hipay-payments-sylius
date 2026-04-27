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
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestProcessorInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PluginPaymentProduct;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

/**
 * Sets payment_product to "paypal" and provider_data with paypal_id.
 * The paypal_id (PayPal order ID) comes from the frontend SDK via the paymentAuthorized event.
 *
 * @see https://developer.hipay.com/online-payments/payment-means/paypal
 */
final class PaypalPaymentMethodProcessorPayment implements PaymentOrderRequestProcessorInterface
{
    public function __construct(
        private readonly EncoderInterface $serializer,
    ) {
    }

    public function process(OrderRequest $orderRequest, PaymentOrderRequestContext $context): void
    {
        $payload = $context->payload;

        $orderRequest->payment_product = PluginPaymentProduct::PAYPAL->value;

        $orderID = $payload['orderID'] ?? null;
        if (is_string($orderID) && '' !== $orderID) {
            $orderRequest->provider_data = $this->serializer->encode(['paypal_id' => $orderID], 'json');
        }
    }
}
