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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sets accept_url, decline_url, pending_url, cancel_url, exception_url and notify_url.
 */
final class CallbackUrlsProcessorPayment implements PaymentOrderRequestProcessorInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function process(OrderRequest $orderRequest, PaymentOrderRequestContext $context): void
    {
        $hashString = (string) $context->paymentRequest->getHash();
        $afterPayUrl = $this->urlGenerator->generate(
            'sylius_shop_order_after_pay',
            ['hash' => $hashString],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $cancelUrl = $this->urlGenerator->generate(
            'sylius_hipay_plugin_shop_cancel_payment',
            ['tokenValue' => $context->getOrder()->getTokenValue()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $orderRequest->accept_url = $afterPayUrl;
        $orderRequest->decline_url = $cancelUrl;
        $orderRequest->pending_url = $this->buildPendingUrl($context);
        $orderRequest->cancel_url = $cancelUrl;
        $orderRequest->exception_url = $afterPayUrl;

        $orderRequest->notify_url = $this->urlGenerator->generate(
            'sylius_hipay_plugin_webhook',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    private function buildPendingUrl(PaymentOrderRequestContext $context): string
    {
        $tokenValue = $context->order->getTokenValue();

        return $this->urlGenerator->generate(
            'sylius_hipay_plugin_awaiting_payment',
            ['tokenValue' => $tokenValue],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
