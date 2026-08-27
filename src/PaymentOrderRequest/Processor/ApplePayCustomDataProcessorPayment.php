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
        $currentCustomData = $orderRequest->custom_data;
        if (is_array($currentCustomData)) {
            // Earlier processors (e.g. CommonFieldsProcessorPayment, priority 50) leave
            // custom_data as a PHP array holding account_sylius_id and payment_id. Preserve
            // it, otherwise the HiPay notification comes back without account_sylius_id and
            // HmacValidator rejects it with a 500. See HIPASYLU004-*.
            $existingCustomData = $currentCustomData;
        } elseif (is_string($currentCustomData) && '' !== $currentCustomData) {
            /** @var array<string, mixed>|null $decoded */
            $decoded = $this->serializer->decode($currentCustomData, 'json');
            if (is_array($decoded)) {
                $existingCustomData = $decoded;
            }
        }

        $existingCustomData['isApplePay'] = 1;

        $orderRequest->custom_data = $this->serializer->encode($existingCustomData, 'json');
    }
}
