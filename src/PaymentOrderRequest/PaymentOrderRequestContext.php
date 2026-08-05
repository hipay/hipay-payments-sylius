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
use HiPay\SyliusHiPayPlugin\Request\RequestContextInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

/**
 * Immutable value object carrying all data that processors may need.
 * Prevents processors from fetching data themselves and keeps signatures clean.
 */
final readonly class PaymentOrderRequestContext implements RequestContextInterface
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

    public function getOrder(): OrderInterface
    {
        return $this->order;
    }

    public function isHostedPageV2(): bool
    {
        $hostedPage = $this->gatewayConfig['hosted_page'] ?? null;
        if (!is_array($hostedPage)) {
            return false;
        }

        return $this->toBoolean($hostedPage['hosted_page_v2'] ?? false);
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return 0 !== (int) $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }
}
