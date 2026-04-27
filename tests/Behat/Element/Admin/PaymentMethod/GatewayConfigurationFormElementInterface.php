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

namespace Tests\HiPay\SyliusHiPayPlugin\Behat\Element\Admin\PaymentMethod;

interface GatewayConfigurationFormElementInterface
{
    public function selectAccount(string $code): void;

    public function selectPaymentProduct(string $product): void;

    public function setTextColor(string $color): void;

    public function checkCardBrand(string $brand): void;

    public function uncheckCardBrand(string $brand): void;

    public function getAccountValue(): ?string;

    public function getPaymentProductValue(): ?string;

    public function getTextColorValue(): ?string;

    public function isCardBrandChecked(string $brand): bool;
}
