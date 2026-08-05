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

namespace HiPay\SyliusHiPayPlugin\Helper;

use HiPay\SyliusHiPayPlugin\Payment\HiPayStatus;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PluginPaymentProduct;
use Sylius\Component\Core\Model\PaymentInterface;

class AwaitingPaymentHelper
{
    /** @var list<string> */
    public const MOBILE_PRODUCTS = [
        PluginPaymentProduct::BANCOMAT_PAY->value,
        PluginPaymentProduct::BIZUM->value,
        PluginPaymentProduct::MBWAY->value,
    ];

    /** @var list<string> */
    public const SUCCESS_STATES = [
        PaymentInterface::STATE_AUTHORIZED,
        PaymentInterface::STATE_COMPLETED,
    ];

    /** @var list<string> */
    public const FAILURE_STATES = [
        PaymentInterface::STATE_FAILED,
        PaymentInterface::STATE_CANCELLED,
        PaymentInterface::STATE_REFUNDED,
    ];

    /** @var list<int> */
    public const DIRECT_STATUSES = [
        HiPayStatus::Authorized->value,
        HiPayStatus::CaptureRequested->value,
        HiPayStatus::Captured->value,
        HiPayStatus::Refused->value,
    ];

    /**
     * @param array<string, mixed> $payload
     */
    public static function isMobileDirectNotification(array $payload, int $status): bool
    {
        // By pass for all payment product
        if (HiPayStatus::AuthorizationRequested->value === $status) {
            return true;
        }

        if (!in_array($status, self::DIRECT_STATUSES, true)) {
            return false;
        }
        $product = $payload['payment_product'] ?? null;

        return in_array($product, self::MOBILE_PRODUCTS, true);
    }

    public static function resolvePaymentProduct(?PaymentInterface $payment): ?string
    {
        if (null === $payment) {
            return null;
        }

        $gatewayConfig = $payment->getMethod()?->getGatewayConfig()?->getConfig() ?? [];
        $product = $gatewayConfig['payment_product'] ?? null;

        return in_array($product, self::MOBILE_PRODUCTS, true) ? $product : 'default';
    }
}
