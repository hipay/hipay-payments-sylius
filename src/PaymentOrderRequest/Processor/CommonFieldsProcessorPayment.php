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
use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\PaymentFallbackDefaultsInterface;
use HiPay\SyliusHiPayPlugin\Util\CurrencyAmount;
use function sprintf;
use Symfony\Component\Clock\ClockInterface;

/**
 * Sets common order fields: orderid, description, amount, currency, shipping, tax,
 * cid, language, sales_channel, payment_connectivity.
 * The `operation` (capture mode) is set by {@see CaptureModeProcessorPayment} and
 * the `source` field by {@see \HiPay\SyliusHiPayPlugin\Request\Processor\SourceProcessor}.
 */
final class CommonFieldsProcessorPayment implements PaymentOrderRequestProcessorInterface
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    public function process(OrderRequest $orderRequest, PaymentOrderRequestContext $context): void
    {
        $order = $context->order;

        $channelCode = $order->getChannel()?->getCode() ?? 'channel_code';
        $number = $order->getNumber() ?? $order->getTokenValue() ?? (string) $order->getId();
        $orderId = sprintf('%s-%s-%s', $number, $channelCode, $this->clock->now()->format('His'));

        $orderRequest->orderid = $orderId;
        $orderRequest->custom_data = [
            'account_sylius_id' => $context->account->getId(),
            'payment_id' => $context->payment->getId(),
        ];
        $orderRequest->description = sprintf('Order #%s', $number);
        $currencyCode = (string) $order->getCurrencyCode();
        $orderRequest->amount = CurrencyAmount::fromSmallestUnit($context->payment->getAmount() ?? 0, $currencyCode);
        $orderRequest->currency = $currencyCode;
        $orderRequest->shipping = CurrencyAmount::fromSmallestUnit($order->getShippingTotal(), $currencyCode);
        $orderRequest->tax = CurrencyAmount::fromSmallestUnit($order->getTaxTotal(), $currencyCode);

        $orderRequest->cid = (string) ($order->getCustomer()?->getId() ?? '');

        $locale = $order->getLocaleCode() ?? PaymentFallbackDefaultsInterface::LANGUAGE_CODE;
        $orderRequest->language = str_replace('-', '_', $locale);
    }
}
