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

namespace HiPay\SyliusHiPayPlugin\Provider;

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

/**
 * Provides available accounts for the Hosted Fields gateway configuration.
 * Used by the Hosted Fields gateway configuration form type.
 */
interface AccountProviderInterface
{
    /**
     * @return array<string, string>
     */
    public function getForChoiceList(): array;

    public function getByCode(string $code): ?AccountInterface;

    public function getByPaymentMethod(PaymentMethodInterface $paymentMethod): ?AccountInterface;

    public function getByPayment(PaymentInterface $payment): ?AccountInterface;
}
