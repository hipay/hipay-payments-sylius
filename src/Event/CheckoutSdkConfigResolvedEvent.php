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

namespace HiPay\SyliusHiPayPlugin\Event;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when the checkout LiveComponent built the HiPay JS SDK bootstrap payload.
 */
final class CheckoutSdkConfigResolvedEvent extends Event
{
    /**
     * @param array<string, mixed> $sdkConfig
     */
    public function __construct(
        private array $sdkConfig,
        private readonly ?PaymentMethodInterface $paymentMethod,
        private readonly ?PaymentInterface $payment,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getSdkConfig(): array
    {
        return $this->sdkConfig;
    }

    /**
     * @param array<string, mixed> $sdkConfig
     */
    public function setSdkConfig(array $sdkConfig): void
    {
        $this->sdkConfig = $sdkConfig;
    }

    public function getPaymentMethod(): ?PaymentMethodInterface
    {
        return $this->paymentMethod;
    }

    public function getPayment(): ?PaymentInterface
    {
        return $this->payment;
    }
}
