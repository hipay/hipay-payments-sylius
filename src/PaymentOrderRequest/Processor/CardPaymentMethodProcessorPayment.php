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
use HiPay\SyliusHiPayPlugin\Exception\InvalidPaymentPayloadException;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestProcessorInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\ThreeDS\ThreeDSMode;
use HiPay\SyliusHiPayPlugin\Security\CardTokenOwnershipGuardInterface;

/**
 * Sets payment_product and paymentMethod (CardTokenPaymentMethod) for card payments.
 * Extracted from CardHandler::configureOrderRequest().
 */
final class CardPaymentMethodProcessorPayment implements PaymentOrderRequestProcessorInterface
{
    public function __construct(
        private readonly CardTokenOwnershipGuardInterface $cardTokenOwnershipGuard,
    ) {
    }

    public function process(OrderRequest $orderRequest, PaymentOrderRequestContext $context): void
    {
        $payload = $context->payload;

        /** @var array<string, mixed> $configuration */
        $configuration = $context->gatewayConfig['configuration'] ?? $context->gatewayConfig;

        $rawPaymentProduct = $payload['payment_product'] ?? 'cb';
        $paymentProduct = is_string($rawPaymentProduct) ? $rawPaymentProduct : 'cb';

        $allowedBrands = $configuration['allowed_brands'] ?? [];
        if (
            array_key_exists('payment_product', $payload) &&
            is_array($allowedBrands) && [] !== $allowedBrands &&
            !in_array($paymentProduct, $allowedBrands, true)
        ) {
            throw new InvalidPaymentPayloadException(
                sprintf('Payment product "%s" is not allowed for this gateway', $paymentProduct),
            );
        }

        $orderRequest->payment_product = $paymentProduct;

        $oneClickEnabled = (bool) ($configuration['one_click_enabled'] ?? false);
        // @phpstan-ignore-next-line
        $orderRequest->one_click = $oneClickEnabled ? (int) ($payload['one_click'] ?? 0) : 0;
        // @phpstan-ignore-next-line
        $orderRequest->multi_use = $oneClickEnabled ? (int) ($payload['multi_use'] ?? 0) : 0;

        $cardTokenMethod = new CardTokenPaymentMethod();
        $rawToken = $payload['token'] ?? '';
        $token = is_string($rawToken) ? $rawToken : '';

        // V2: reject a token that is already bound to another customer.
        $this->cardTokenOwnershipGuard->assertTokenBelongsToCustomer($token, $context->order->getCustomer());

        $cardTokenMethod->cardtoken = $token;

        $rawMode = $configuration['three_ds_mode'] ?? $configuration['3ds_mode'] ?? ThreeDSMode::IF_AVAILABLE;
        $threeDsMode = is_string($rawMode) ? $rawMode : ThreeDSMode::IF_AVAILABLE;
        $cardTokenMethod->authentication_indicator = ThreeDSMode::toHiPayIndicator($threeDsMode);

        $orderRequest->paymentMethod = $cardTokenMethod;
    }
}
