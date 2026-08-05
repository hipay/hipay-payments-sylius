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

interface AlmaLimitsProviderInterface
{
    /**
     * Fetch Alma min/max basket amounts from the HiPay API.
     * Returns amounts in major currency (EUR), or null on failure / unsupported product.
     *
     * @return array{min: float, max: float}|null
     */
    public function getLimits(string $accountCode, string $paymentProduct): ?array;
}
