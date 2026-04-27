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

use Composer\InstalledVersions;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestProcessorInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\PaymentFallbackDefaultsInterface;
use function sprintf;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Sets common order fields: orderid, description, amount, currency, shipping, tax,
 * operation, source, cid, language, sales_channel, payment_connectivity.
 */
final class CommonFieldsProcessorPayment implements PaymentOrderRequestProcessorInterface
{
    private const PLUGIN_VERSION = '1.0.0';

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
        $orderRequest->custom_data = ['account_sylius_id' => $context->account->getId()];
        $orderRequest->description = sprintf('Order #%s', $number);
        $orderRequest->amount = $context->payment->getAmount() / 100;
        $orderRequest->currency = (string) $order->getCurrencyCode();
        $orderRequest->shipping = $order->getShippingTotal() / 100;
        $orderRequest->tax = $order->getTaxTotal() / 100;

        $orderRequest->operation = PaymentRequestInterface::ACTION_AUTHORIZE === $context->action
            ? 'Authorization'
            : 'Sale';

        $orderRequest->cid = (string) ($order->getCustomer()?->getId() ?? '');

        $locale = $order->getLocaleCode() ?? PaymentFallbackDefaultsInterface::LANGUAGE_CODE;
        $orderRequest->language = str_replace('-', '_', $locale);

        $orderRequest->source = $this->buildSource();
    }

    /**
     * @return array{source: string, brand: string, brand_version: string, integration_version: string}
     */
    private function buildSource(): array
    {
        $syliusVersion = InstalledVersions::getPrettyVersion('sylius/sylius')
            ?? InstalledVersions::getPrettyVersion('sylius/core-bundle')
            ?? 'unknown';

        return [
            'source' => 'CMS',
            'brand' => 'Sylius',
            'brand_version' => $syliusVersion,
            'integration_version' => self::PLUGIN_VERSION,
        ];
    }
}
