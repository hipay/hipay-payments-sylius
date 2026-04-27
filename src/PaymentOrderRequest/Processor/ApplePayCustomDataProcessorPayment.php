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
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

/**
 * Tags Apple Pay transactions with custom_data.isApplePay = 1 so HiPay
 * can distinguish them from regular card payments (both share the same
 * card-network payment_product like "visa" or "mastercard").
 */
final class ApplePayCustomDataProcessorPayment implements PaymentOrderRequestProcessorInterface
{
    public function __construct(
        private readonly EncoderInterface&DecoderInterface $serializer,
    ) {
    }

    public function process(OrderRequest $orderRequest, PaymentOrderRequestContext $context): void
    {
        unset($context);

        $existingCustomData = [];
        if (is_string($orderRequest->custom_data) && '' !== $orderRequest->custom_data) {
            /** @var array<string, mixed>|null $decoded */
            $decoded = $this->serializer->decode($orderRequest->custom_data, 'json');
            if (is_array($decoded)) {
                $existingCustomData = $decoded;
            }
        }

        $existingCustomData['isApplePay'] = 1;

        $orderRequest->custom_data = $this->serializer->encode($existingCustomData, 'json');
    }
}
