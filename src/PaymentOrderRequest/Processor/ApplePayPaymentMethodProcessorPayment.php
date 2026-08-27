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
use HiPay\SyliusHiPayPlugin\Security\CardTokenOwnershipGuardInterface;

/**
 * Sets payment_product and CardTokenPaymentMethod for Apple Pay transactions.
 * Apple Pay authenticates via Face ID / Touch ID — no 3DS or one-click flags needed.
 * The HiPay token (from the paymentAuthorized event) has the same structure as getPaymentData().
 *
 * @see https://developer.hipay.com/online-payments/payment-means/apple-pay-web
 */
final class ApplePayPaymentMethodProcessorPayment implements PaymentOrderRequestProcessorInterface
{
    public function __construct(
        private readonly CardTokenOwnershipGuardInterface $cardTokenOwnershipGuard,
    ) {
    }

    public function process(OrderRequest $orderRequest, PaymentOrderRequestContext $context): void
    {
        $payload = $context->payload;

        $rawPaymentProduct = $payload['payment_product'] ?? 'cb';
        $orderRequest->payment_product = is_string($rawPaymentProduct) ? $rawPaymentProduct : 'cb';

        $cardTokenMethod = new CardTokenPaymentMethod();
        $rawToken = $payload['token'] ?? '';
        $token = is_string($rawToken) ? $rawToken : '';

        $this->cardTokenOwnershipGuard->assertTokenBelongsToCustomer($token, $context->order->getCustomer());

        $cardTokenMethod->cardtoken = $token;

        $orderRequest->paymentMethod = $cardTokenMethod;
    }
}
