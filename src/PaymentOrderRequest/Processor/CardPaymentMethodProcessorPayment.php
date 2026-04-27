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
use HiPay\Fullservice\Gateway\Request\PaymentMethod\CardTokenPaymentMethod;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestProcessorInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\ThreeDS\ThreeDSMode;

/**
 * Sets payment_product and paymentMethod (CardTokenPaymentMethod) for card payments.
 * Extracted from CardHandler::configureOrderRequest().
 */
final class CardPaymentMethodProcessorPayment implements PaymentOrderRequestProcessorInterface
{
    public function process(OrderRequest $orderRequest, PaymentOrderRequestContext $context): void
    {
        $payload = $context->payload;

        /** @var array<string, mixed> $configuration */
        $configuration = $context->gatewayConfig['configuration'] ?? $context->gatewayConfig;

        $rawPaymentProduct = $payload['payment_product'] ?? 'cb';
        $orderRequest->payment_product = is_string($rawPaymentProduct) ? $rawPaymentProduct : 'cb';
        // @phpstan-ignore-next-line
        $orderRequest->one_click = (int) ($payload['one_click'] ?? 0);
        // @phpstan-ignore-next-line
        $orderRequest->multi_use = (int) ($payload['multi_use'] ?? 0);

        $cardTokenMethod = new CardTokenPaymentMethod();
        $rawToken = $payload['token'] ?? '';
        $cardTokenMethod->cardtoken = is_string($rawToken) ? $rawToken : '';

        $rawMode = $configuration['three_ds_mode'] ?? $configuration['3ds_mode'] ?? ThreeDSMode::IF_AVAILABLE;
        $threeDsMode = is_string($rawMode) ? $rawMode : ThreeDSMode::IF_AVAILABLE;
        $cardTokenMethod->authentication_indicator = ThreeDSMode::toHiPayIndicator($threeDsMode);

        $orderRequest->paymentMethod = $cardTokenMethod;
    }
}
