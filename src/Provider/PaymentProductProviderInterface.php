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

interface PaymentProductProviderInterface
{
    /**
     * @return array<string>
     */
    public function getAvailableProductCodesByAccountCode(string $accountCode): array;

    /**
     * @return array<string, string>
     */
    public function getAllForChoiceList(string $accountCode): array;
}
