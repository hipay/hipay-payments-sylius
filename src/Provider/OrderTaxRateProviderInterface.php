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

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Model\AdjustableInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;

interface OrderTaxRateProviderInterface
{
    /**
     * Returns the tax rates of an order element (item unit, shipment), as configured in Sylius
     * and not recomputed from rounded amounts. `amount` is a fraction (0.2 for 20 %).
     * The configured rate is only used as a fallback for a fully discounted element (Sylius sets no
     * tax adjustment then); a billed element without tax adjustment is tax free and yields no rate.
     *
     * @param AdjustableInterface $adjustable Element carrying the tax adjustments set by Sylius
     * @param TaxableInterface|null $taxable Taxed subject (variant, shipping method), used when no tax adjustment was set on a fully discounted element
     *
     * @return list<array{amount: float, includedInPrice: bool}>
     */
    public function getTaxRates(OrderInterface $order, AdjustableInterface $adjustable, ?TaxableInterface $taxable): array;
}
