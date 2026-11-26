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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Provider;

use Doctrine\Common\Collections\ArrayCollection;
use HiPay\SyliusHiPayPlugin\Provider\OrderTaxRateProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Addressing\Matcher\ZoneMatcherInterface;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\Adjustment;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Core\Model\Scope;
use Sylius\Component\Core\Model\TaxRateInterface;
use Sylius\Component\Core\Provider\ZoneProviderInterface;
use Sylius\Component\Core\Resolver\TaxationAddressResolverInterface;
use Sylius\Component\Order\Model\AdjustableInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

final class OrderTaxRateProviderTest extends TestCase
{
    private TaxRateResolverInterface&MockObject $taxRateResolver;

    private TaxationAddressResolverInterface&MockObject $taxationAddressResolver;

    private ZoneMatcherInterface&MockObject $zoneMatcher;

    private ZoneProviderInterface&MockObject $defaultTaxZoneProvider;

    private OrderInterface&MockObject $order;

    private OrderTaxRateProvider $provider;

    protected function setUp(): void
    {
        $this->taxRateResolver = $this->createMock(TaxRateResolverInterface::class);
        $this->taxationAddressResolver = $this->createMock(TaxationAddressResolverInterface::class);
        $this->zoneMatcher = $this->createMock(ZoneMatcherInterface::class);
        $this->defaultTaxZoneProvider = $this->createMock(ZoneProviderInterface::class);
        $this->order = $this->createMock(OrderInterface::class);

        $this->provider = new OrderTaxRateProvider(
            $this->taxRateResolver,
            $this->taxationAddressResolver,
            $this->zoneMatcher,
            $this->defaultTaxZoneProvider,
        );
    }

    public function testReadsTheRateOfATaxExcludedAdjustment(): void
    {
        $adjustable = $this->createAdjustable([$this->createTaxAdjustment('vat_20', 0.2, false)]);
        $this->taxRateResolver->expects($this->never())->method('resolve');

        $taxRates = $this->provider->getTaxRates($this->order, $adjustable, $this->createMock(TaxableInterface::class));

        $this->assertSame([['amount' => 0.2, 'includedInPrice' => false]], $taxRates);
    }

    public function testReadsTheRateOfATaxIncludedAdjustment(): void
    {
        $adjustable = $this->createAdjustable([$this->createTaxAdjustment('vat_20', 0.2, true)]);

        $taxRates = $this->provider->getTaxRates($this->order, $adjustable, null);

        $this->assertSame([['amount' => 0.2, 'includedInPrice' => true]], $taxRates);
    }

    public function testReturnsEveryDistinctRateOnce(): void
    {
        $adjustable = $this->createAdjustable([
            $this->createTaxAdjustment('vat_20', 0.2, false),
            $this->createTaxAdjustment('vat_20', 0.2, false),
            $this->createTaxAdjustment('eco_tax', 0.055, false),
        ]);

        $taxRates = $this->provider->getTaxRates($this->order, $adjustable, null);

        $this->assertSame(
            [
                ['amount' => 0.2, 'includedInPrice' => false],
                ['amount' => 0.055, 'includedInPrice' => false],
            ],
            $taxRates,
        );
    }

    public function testIgnoresTaxAdjustmentsWithoutRateDetails(): void
    {
        $adjustment = new Adjustment();
        $adjustment->setType(AdjustmentInterface::TAX_ADJUSTMENT);
        $adjustment->setAmount(48);

        $taxRates = $this->provider->getTaxRates($this->order, $this->createAdjustable([$adjustment]), null);

        $this->assertSame([], $taxRates);
    }

    public function testFallsBackOnTheConfiguredRateOfTheAddressZone(): void
    {
        $taxable = $this->createMock(TaxableInterface::class);
        $address = $this->createMock(AddressInterface::class);
        $zone = $this->createMock(ZoneInterface::class);
        $this->taxationAddressResolver->method('getTaxationAddressFromOrder')->with($this->order)->willReturn($address);
        $this->zoneMatcher->method('match')->with($address, Scope::TAX)->willReturn($zone);
        $this->defaultTaxZoneProvider->expects($this->never())->method('getZone');
        $this->taxRateResolver->method('resolve')->with($taxable, ['zone' => $zone])->willReturn($this->createTaxRate(0.2, false));

        $taxRates = $this->provider->getTaxRates($this->order, $this->createAdjustable([]), $taxable);

        $this->assertSame([['amount' => 0.2, 'includedInPrice' => false]], $taxRates);
    }

    public function testFallsBackOnTheDefaultTaxZoneWithoutAddress(): void
    {
        $taxable = $this->createMock(TaxableInterface::class);
        $zone = $this->createMock(ZoneInterface::class);
        $this->taxationAddressResolver->method('getTaxationAddressFromOrder')->willReturn(null);
        $this->zoneMatcher->expects($this->never())->method('match');
        $this->defaultTaxZoneProvider->method('getZone')->with($this->order)->willReturn($zone);
        $this->taxRateResolver->method('resolve')->with($taxable, ['zone' => $zone])->willReturn($this->createTaxRate(0.055, true));

        $taxRates = $this->provider->getTaxRates($this->order, $this->createAdjustable([]), $taxable);

        $this->assertSame([['amount' => 0.055, 'includedInPrice' => true]], $taxRates);
    }

    public function testReturnsNoRateWithoutTaxZone(): void
    {
        $this->taxationAddressResolver->method('getTaxationAddressFromOrder')->willReturn(null);
        $this->defaultTaxZoneProvider->method('getZone')->willReturn(null);
        $this->taxRateResolver->expects($this->never())->method('resolve');

        $taxRates = $this->provider->getTaxRates($this->order, $this->createAdjustable([]), $this->createMock(TaxableInterface::class));

        $this->assertSame([], $taxRates);
    }

    public function testReturnsNoRateForAnUntaxedSubject(): void
    {
        $this->taxationAddressResolver->method('getTaxationAddressFromOrder')->willReturn(null);
        $this->defaultTaxZoneProvider->method('getZone')->willReturn($this->createMock(ZoneInterface::class));
        $this->taxRateResolver->method('resolve')->willReturn(null);

        $taxRates = $this->provider->getTaxRates($this->order, $this->createAdjustable([]), $this->createMock(TaxableInterface::class));

        $this->assertSame([], $taxRates);
    }

    public function testDoesNotReadTheConfigurationWithoutTaxedSubject(): void
    {
        $this->taxationAddressResolver->expects($this->never())->method('getTaxationAddressFromOrder');

        $this->assertSame([], $this->provider->getTaxRates($this->order, $this->createAdjustable([]), null));
    }

    public function testReturnsNoRateForABilledUnitWithoutTaxAdjustment(): void
    {
        $this->taxationAddressResolver->expects($this->never())->method('getTaxationAddressFromOrder');
        $this->taxRateResolver->expects($this->never())->method('resolve');

        $taxRates = $this->provider->getTaxRates($this->order, $this->createUnit(1000), $this->createMock(TaxableInterface::class));

        $this->assertSame([], $taxRates);
    }

    public function testFallsBackOnTheConfiguredRateForAFullyDiscountedUnit(): void
    {
        $taxable = $this->createMock(TaxableInterface::class);
        $this->taxationAddressResolver->method('getTaxationAddressFromOrder')->willReturn(null);
        $this->defaultTaxZoneProvider->method('getZone')->willReturn($this->createMock(ZoneInterface::class));
        $this->taxRateResolver->method('resolve')->willReturn($this->createTaxRate(0.2, true));

        $taxRates = $this->provider->getTaxRates($this->order, $this->createUnit(0), $taxable);

        $this->assertSame([['amount' => 0.2, 'includedInPrice' => true]], $taxRates);
    }

    public function testReturnsNoRateForABilledShipmentWithoutTaxAdjustment(): void
    {
        $this->taxRateResolver->expects($this->never())->method('resolve');

        $taxRates = $this->provider->getTaxRates($this->order, $this->createAdjustable([], 500), $this->createMock(TaxableInterface::class));

        $this->assertSame([], $taxRates);
    }

    public function testFallsBackOnTheConfiguredRateForAFreeShipment(): void
    {
        $this->taxationAddressResolver->method('getTaxationAddressFromOrder')->willReturn(null);
        $this->defaultTaxZoneProvider->method('getZone')->willReturn($this->createMock(ZoneInterface::class));
        $this->taxRateResolver->method('resolve')->willReturn($this->createTaxRate(0.2, true));

        $taxRates = $this->provider->getTaxRates($this->order, $this->createAdjustable([], 0), $this->createMock(TaxableInterface::class));

        $this->assertSame([['amount' => 0.2, 'includedInPrice' => true]], $taxRates);
    }

    private function createUnit(int $total): OrderItemUnitInterface
    {
        $unit = $this->createMock(OrderItemUnitInterface::class);
        $unit->method('getAdjustments')->with(AdjustmentInterface::TAX_ADJUSTMENT)->willReturn(new ArrayCollection());
        $unit->method('getTotal')->willReturn($total);

        return $unit;
    }

    /**
     * @param list<AdjustmentInterface> $taxAdjustments
     */
    private function createAdjustable(array $taxAdjustments, int $adjustmentsTotal = 0): AdjustableInterface
    {
        $adjustable = $this->createMock(AdjustableInterface::class);
        $adjustable
            ->method('getAdjustments')
            ->with(AdjustmentInterface::TAX_ADJUSTMENT)
            ->willReturn(new ArrayCollection($taxAdjustments));
        $adjustable->method('getAdjustmentsTotal')->willReturn($adjustmentsTotal);

        return $adjustable;
    }

    private function createTaxAdjustment(string $code, float $amount, bool $includedInPrice): AdjustmentInterface
    {
        $adjustment = new Adjustment();
        $adjustment->setType(AdjustmentInterface::TAX_ADJUSTMENT);
        $adjustment->setAmount(48);
        $adjustment->setNeutral($includedInPrice);
        $adjustment->setDetails(['taxRateCode' => $code, 'taxRateName' => $code, 'taxRateAmount' => $amount]);

        return $adjustment;
    }

    private function createTaxRate(float $amount, bool $includedInPrice): TaxRateInterface
    {
        $taxRate = $this->createMock(TaxRateInterface::class);
        $taxRate->method('getAmount')->willReturn($amount);
        $taxRate->method('isIncludedInPrice')->willReturn($includedInPrice);

        return $taxRate;
    }
}
