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

namespace HiPay\SyliusHiPayPlugin\PaymentOrderRequest;

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

/**
 * Immutable value object carrying all data that processors may need.
 * Prevents processors from fetching data themselves and keeps signatures clean.
 */
final readonly class PaymentOrderRequestContext
{
    /**
     * @param array<string, mixed> $payload       Frontend data (token, browser_info, device_fingerprint, payment_product...)
     * @param array<string, mixed> $gatewayConfig  Gateway configuration from PaymentMethod
     */
    public function __construct(
        public OrderInterface $order,
        public PaymentInterface $payment,
        public PaymentRequestInterface $paymentRequest,
        public AccountInterface $account,
        public string $paymentProduct,
        public array $payload,
        public array $gatewayConfig,
        public string $action,
    ) {
    }
}
