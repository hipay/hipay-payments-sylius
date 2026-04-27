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

namespace HiPay\SyliusHiPayPlugin\PaymentProduct;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

interface PaymentProductHandlerInterface
{
    /**
     * Check if this handler supports the given HiPay payment product.
     */
    public function supports(string $paymentProduct): bool;

    /**
     * Unique identifier code for this payment product.
     */
    public function getCode(): string;

    /**
     * Translation key for the payment product name.
     */
    public function getName(): string;

    /**
     * Form type class for admin configuration (null if no specific config).
     */
    public function getFormType(): ?string;

    /**
     * Configuration for HiPay JS SDK initialization.
     *
     * Some products (e.g. PayPal) need payment-level data (amount, currency)
     * at SDK init time; pass $payment when available.
     *
     * @return array<string, mixed>
     */
    public function getJsInitConfig(PaymentMethodInterface $paymentMethod, ?PaymentInterface $payment = null): array;

    /**
     * ISO country codes where available (empty = worldwide).
     * Used by GeneralConfigurationType to restrict admin form choices.
     *
     * @return string[]
     */
    public function getAvailableCountries(): array;

    /**
     * ISO currency codes where available (empty = all).
     * Used by GeneralConfigurationType to restrict admin form choices.
     *
     * @return string[]
     */
    public function getAvailableCurrencies(): array;
}
