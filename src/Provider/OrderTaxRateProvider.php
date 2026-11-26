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

use Sylius\Component\Addressing\Matcher\ZoneMatcherInterface;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Core\Model\Scope;
use Sylius\Component\Core\Provider\ZoneProviderInterface;
use Sylius\Component\Core\Resolver\TaxationAddressResolverInterface;
use Sylius\Component\Order\Model\AdjustableInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

final class OrderTaxRateProvider implements OrderTaxRateProviderInterface
{
    public function __construct(
        private readonly TaxRateResolverInterface $taxRateResolver,
        private readonly TaxationAddressResolverInterface $taxationAddressResolver,
        private readonly ZoneMatcherInterface $zoneMatcher,
        private readonly ZoneProviderInterface $defaultTaxZoneProvider,
    ) {
    }

    public function getTaxRates(OrderInterface $order, AdjustableInterface $adjustable, ?TaxableInterface $taxable): array
    {
        $taxRates = $this->getAppliedTaxRates($adjustable);
        if ([] !== $taxRates || null === $taxable) {
            return $taxRates;
        }

        // Sylius sets no tax adjustment when the tax amount is zero because the element is
        // fully discounted: the rate is then read from the tax configuration, as Sylius would.
        // An element still billed without any tax adjustment is tax free (customer exemption,
        // reverse charge): it must not get a rate from the configuration.
        if ($this->getBilledTotal($adjustable) > 0) {
            return [];
        }

        $zone = $this->getTaxZone($order);
        if (null === $zone) {
            return [];
        }

        $taxRate = $this->taxRateResolver->resolve($taxable, ['zone' => $zone]);
        if (null === $taxRate) {
            return [];
        }

        return [['amount' => $taxRate->getAmount(), 'includedInPrice' => $taxRate->isIncludedInPrice()]];
    }

    /**
     * Amount billed for the element: the unit total for an item unit, the sum of the
     * adjustments (shipping charge, promotions, tax) for a shipment, which has no total of its own.
     */
    private function getBilledTotal(AdjustableInterface $adjustable): int
    {
        if ($adjustable instanceof OrderItemUnitInterface) {
            return $adjustable->getTotal();
        }

        return $adjustable->getAdjustmentsTotal();
    }

    /**
     * Reads the rates stored by Sylius in the details of the tax adjustments.
     *
     * @return list<array{amount: float, includedInPrice: bool}>
     */
    private function getAppliedTaxRates(AdjustableInterface $adjustable): array
    {
        $taxRates = [];
        foreach ($adjustable->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT) as $taxAdjustment) {
            $details = $taxAdjustment->getDetails();
            $amount = $details['taxRateAmount'] ?? null;
            if (!is_numeric($amount)) {
                continue;
            }

            // A rate split over several adjustments must be counted once.
            $code = (string) ($details['taxRateCode'] ?? $amount);
            $taxRates[$code] = ['amount' => (float) $amount, 'includedInPrice' => $taxAdjustment->isNeutral()];
        }

        return array_values($taxRates);
    }

    /**
     * Same zone resolution as the Sylius order taxes processor.
     */
    private function getTaxZone(OrderInterface $order): ?ZoneInterface
    {
        $zone = null;
        $taxationAddress = $this->taxationAddressResolver->getTaxationAddressFromOrder($order);
        if (null !== $taxationAddress) {
            $zone = $this->zoneMatcher->match($taxationAddress, Scope::TAX);
        }

        return $zone ?? $this->defaultTaxZoneProvider->getZone($order);
    }
}
