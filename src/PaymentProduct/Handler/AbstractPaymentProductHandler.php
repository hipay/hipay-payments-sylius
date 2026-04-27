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

namespace HiPay\SyliusHiPayPlugin\PaymentProduct\Handler;

use Sylius\Component\Core\Model\PaymentInterface;

class AbstractPaymentProductHandler
{
    protected string $code;

    protected string $name;

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function supports(string $paymentProduct): bool
    {
        return $paymentProduct === $this->code;
    }

    public function getFormType(): ?string
    {
        return null;
    }

    public function getAvailableCountries(): array
    {
        return [];
    }

    public function getAvailableCurrencies(): array
    {
        return [];
    }

    protected function getGatewayConfig(PaymentInterface $payment): array
    {
        return $payment->getMethod()?->getGatewayConfig()?->getConfig() ?? [];
    }
}
