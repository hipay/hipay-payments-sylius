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

namespace Tests\HiPay\SyliusHiPayPlugin\Integration\PaymentOrderRequest\Processor;

use HiPay\Fullservice\Gateway\Model\Cart\Cart;
use HiPay\Fullservice\Gateway\Request\Maintenance\MaintenanceRequest;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

/**
 * HIPASYLU005-8: tax mapping of the product lines (unit_price including tax, real tax rate,
 * VAT never sent as a discount), with tax excluded from and included in the Sylius prices.
 */
final class BasketProcessorPaymentTaxTest extends BasketTestCase
{
    /**
     * Merchant case: 2.42 EUR excluding tax, 20 % VAT, quantity 1, no promotion.
     * HiPay rejected the line sent with unit_price 2.42, tax_rate 19.83 and discount 0.48.
     */
    public function testTaxExcludedItemWithoutPromotion(): void
    {
        $taxCategory = $this->createTaxCategory('vat_20', 0.2, false);
        $variant = $this->createVariant('TB01378', 242, $taxCategory);

        $order = $this->createOrder([[$variant, 1]]);

        // Guards: the Sylius side matches what the merchant describes.
        $item = $order->getItems()->first();
        self::assertInstanceOf(OrderItemInterface::class, $item);
        self::assertSame(242, $item->getUnitPrice(), 'Sylius unit price is expected excluding tax.');
        self::assertSame(48, $item->getTaxTotal(), 'Sylius VAT is expected as a 0.48 EUR tax adjustment.');
        self::assertSame(290, $item->getTotal(), 'Sylius item total is expected including tax.');
        self::assertSame(
            0,
            $order->getAdjustmentsTotalRecursively(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT)
            + $order->getAdjustmentsTotalRecursively(AdjustmentInterface::ORDER_ITEM_PROMOTION_ADJUSTMENT)
            + $order->getAdjustmentsTotalRecursively(AdjustmentInterface::ORDER_UNIT_PROMOTION_ADJUSTMENT),
            'No promotion is expected on the order.',
        );

        $goods = $this->goods($this->buildBasket($order));

        self::assertCount(1, $goods);
        self::assertSame('TB01378', $goods[0]->getProductReference());
        $this->assertLine($goods[0], quantity: 1, unitPrice: 2.9, taxRate: 20.0, discount: 0.0, totalAmount: 2.9);
    }

    /**
     * Existing behaviour with includedInPrice = true: the neutral tax adjustment must not
     * become a discount, and the rate must be the configured one (not 0.48 / 2.42 = 19.83).
     */
    public function testTaxIncludedItemWithoutPromotion(): void
    {
        $taxCategory = $this->createTaxCategory('vat_20_included', 0.2, true);
        $variant = $this->createVariant('TB01378', 290, $taxCategory);

        $order = $this->createOrder([[$variant, 1]]);
        self::assertSame(290, $order->getTotal());

        $goods = $this->goods($this->buildBasket($order));

        self::assertCount(1, $goods);
        $this->assertLine($goods[0], quantity: 1, unitPrice: 2.9, taxRate: 20.0, discount: 0.0, totalAmount: 2.9);
    }

    /**
     * 3 x 2.42 EUR excluding tax: Sylius taxes the item total (7.26 + 1.45 = 8.71), which is not
     * divisible by 3. HiPay rejects the product split on two lines ("The product reference must
     * be unique per item") and a unit_price of 2.90 ("The amount provided for the article is not
     * correct"): the single line carries a unit_price with one more decimal, like the other
     * HiPay modules, and no fake discount.
     */
    public function testQuantityGreaterThanOneWithRoundingRemainder(): void
    {
        $taxCategory = $this->createTaxCategory('vat_20', 0.2, false);
        $variant = $this->createVariant('TB01378', 242, $taxCategory);

        $order = $this->createOrder([[$variant, 3]]);
        self::assertSame(871, $order->getTotal());

        $items = $this->buildBasket($order);
        $goods = $this->goods($items);

        self::assertCount(1, $goods);
        self::assertSame('TB01378', $goods[0]->getProductReference());
        $this->assertLine($goods[0], quantity: 3, unitPrice: 2.903, taxRate: 20.0, discount: 0.0, totalAmount: 8.71);
        $this->assertBasketLinesAreConsistent($items, 'EUR');
        $this->assertBasketTotalMatchesOrder($items, $order);
    }

    /**
     * 30 x 2.43 EUR excluding tax at 5.5 %: 76.91 including tax. The closest unit_price with one
     * more decimal (2.564) is 0.01 away from the total once multiplied, which HiPay rejects. The
     * unit_price is rounded up at currency precision and the remainder joins the discount.
     */
    public function testLargeQuantityRemainderIsAbsorbedByTheDiscount(): void
    {
        $taxCategory = $this->createTaxCategory('vat_5_5', 0.055, false);
        $variant = $this->createVariant('TB01378', 243, $taxCategory);

        $order = $this->createOrder([[$variant, 30]]);
        self::assertSame(7691, $order->getTotal());

        $items = $this->buildBasket($order);
        $goods = $this->goods($items);

        self::assertCount(1, $goods);
        $this->assertLine($goods[0], quantity: 30, unitPrice: 2.57, taxRate: 5.5, discount: -0.19, totalAmount: 76.91);
        $this->assertBasketLinesAreConsistent($items, 'EUR');
        $this->assertBasketTotalMatchesOrder($items, $order);
    }

    /**
     * 19 x 1.00 EUR excluding tax at 5.5 %: 20.05 including tax (19.00 + 1.05). The closest
     * unit_price with one more decimal (1.055) is half a cent away from the total once multiplied,
     * which HiPay rejects. The unit_price is rounded up at currency precision (1.06) and the
     * remainder joins the discount: 19 x 1.06 - 0.09 = 20.05.
     */
    public function testLargeQuantityOfAReducedRateItemFallsBackOnTheRoundedUpUnitPrice(): void
    {
        $taxCategory = $this->createTaxCategory('vat_5_5', 0.055, false);
        $variant = $this->createVariant('TB01378', 100, $taxCategory);

        $order = $this->createOrder([[$variant, 19]]);
        self::assertSame(2005, $order->getTotal());

        $items = $this->buildBasket($order);
        $goods = $this->goods($items);

        self::assertCount(1, $goods);
        $this->assertLine($goods[0], quantity: 19, unitPrice: 1.06, taxRate: 5.5, discount: -0.09, totalAmount: 20.05);
        $this->assertBasketLinesAreConsistent($items, 'EUR');
        $this->assertBasketTotalMatchesOrder($items, $order);
    }

    /**
     * Tax exempt order (customer exemption, reverse charge plugin): the taxes were removed from the
     * order, so Sylius carries no tax adjustment while the items are fully billed. The line must
     * not get the configured 20 % rate: tax_rate is 0 and unit_price is the amount really paid.
     */
    public function testTaxExemptOrderIsSentWithoutTaxRate(): void
    {
        $taxCategory = $this->createTaxCategory('vat_20', 0.2, false);
        $variant = $this->createVariant('TB01378', 242, $taxCategory);

        $order = $this->createOrder([[$variant, 3]]);
        $order->removeAdjustmentsRecursively(AdjustmentInterface::TAX_ADJUSTMENT);
        $order->recalculateItemsTotal();
        self::assertSame(726, $order->getTotal(), 'The exempt order is expected to be billed excluding tax.');

        $items = $this->buildBasket($order);
        $goods = $this->goods($items);

        self::assertCount(1, $goods);
        $this->assertLine($goods[0], quantity: 3, unitPrice: 2.42, taxRate: 0.0, discount: 0.0, totalAmount: 7.26);
        $this->assertBasketLinesAreConsistent($items, 'EUR');
        $this->assertBasketTotalMatchesOrder($items, $order);
    }

    /**
     * Whatever the quantity, the product stays on one line that HiPay accepts.
     *
     * @dataProvider quantityProvider
     */
    public function testAnyQuantityYieldsOneConsistentLine(int $priceExcludingTax, float $taxRateAmount, int $quantity): void
    {
        $taxCategory = $this->createTaxCategory('vat', $taxRateAmount, false);
        $variant = $this->createVariant('TB01378', $priceExcludingTax, $taxCategory);

        $order = $this->createOrder([[$variant, $quantity]]);

        $items = $this->buildBasket($order);

        self::assertCount(1, $this->goods($items));
        self::assertSame($quantity, (int) $this->goods($items)[0]->getQuantity());
        $this->assertBasketLinesAreConsistent($items, 'EUR');
        $this->assertBasketTotalMatchesOrder($items, $order);
    }

    /**
     * @return iterable<string, array{int, float, int}>
     */
    public static function quantityProvider(): iterable
    {
        foreach ([[242, 0.2], [243, 0.055], [1999, 0.2], [833, 0.2]] as [$price, $rate]) {
            foreach ([2, 3, 6, 7, 11, 15, 20, 60] as $quantity) {
                yield sprintf('%d x %d at %s %%', $quantity, $price, $rate * 100) => [$price, $rate, $quantity];
            }
        }
    }

    /**
     * Each line carries the rate of its own tax category.
     */
    public function testSeveralTaxRates(): void
    {
        $standard = $this->createVariant('STANDARD', 1000, $this->createTaxCategory('vat_20', 0.2, false));
        $reduced = $this->createVariant('REDUCED', 1000, $this->createTaxCategory('vat_5_5', 0.055, false));

        $order = $this->createOrder([[$standard, 1], [$reduced, 1]]);
        self::assertSame(2255, $order->getTotal());

        $items = $this->buildBasket($order);
        $goods = $this->goods($items);

        self::assertCount(2, $goods);
        $this->assertLine($goods[0], quantity: 1, unitPrice: 12.0, taxRate: 20.0, discount: 0.0, totalAmount: 12.0);
        $this->assertLine($goods[1], quantity: 1, unitPrice: 10.55, taxRate: 5.5, discount: 0.0, totalAmount: 10.55);
        $this->assertBasketTotalMatchesOrder($items, $order);
    }

    /**
     * Sylius core applies one rate per item, but a project can stack a second tax adjustment
     * (eco tax, local tax). The item then carries 20 % and 5.5 %: the basket must either split
     * the line per rate or send the combined rate, never a rate recomputed from rounded amounts.
     */
    public function testSeveralTaxRatesOnTheSameItem(): void
    {
        $variant = $this->createVariant('MULTI', 1000, $this->createTaxCategory('vat_20', 0.2, false));
        $order = $this->createOrder([[$variant, 1]]);

        $unit = $order->getItemUnits()->first();
        self::assertNotFalse($unit);
        $unit->addAdjustment(self::getContainer()->get('sylius.factory.adjustment')->createWithData(
            AdjustmentInterface::TAX_ADJUSTMENT,
            'Eco tax (5.5%)',
            55,
            false,
            ['taxRateCode' => 'eco_tax', 'taxRateName' => 'Eco tax', 'taxRateAmount' => 0.055],
        ));
        self::assertSame(1255, $order->getTotal());

        $items = $this->buildBasket($order);
        $goods = $this->goods($items);

        $this->assertBasketLinesAreConsistent($items, 'EUR');
        $this->assertBasketTotalMatchesOrder($items, $order);
        self::assertNotEmpty($goods);
        foreach ($goods as $good) {
            self::assertSame('MULTI', $good->getProductReference());
            self::assertContainsEquals(
                $good->getTaxRate(),
                [20.0, 5.5, 25.5],
                'tax_rate must be a real rate of the item (per-rate line) or their sum (single line).',
            );
        }
    }

    /**
     * The basket processor also serves the capture pipeline: a capture request must carry
     * the same product line as the order request.
     */
    public function testCaptureRequestCarriesTheSameBasket(): void
    {
        $taxCategory = $this->createTaxCategory('vat_20', 0.2, false);
        $variant = $this->createVariant('TB01378', 242, $taxCategory);
        $order = $this->createOrder([[$variant, 1]]);

        $request = new MaintenanceRequest();
        $this->processBasket($request, $order);

        self::assertInstanceOf(Cart::class, $request->basket);
        $goods = $this->goods(array_values($request->basket->getAllItems()));

        self::assertCount(1, $goods);
        $this->assertLine($goods[0], quantity: 1, unitPrice: 2.9, taxRate: 20.0, discount: 0.0, totalAmount: 2.9);
    }

    /**
     * @dataProvider currencyProvider
     */
    public function testCurrencyDecimals(
        string $currencyCode,
        int $priceExcludingTax,
        float $taxRateAmount,
        float $expectedAmount,
        float $expectedTaxRate,
    ): void {
        $this->channel = $this->createChannel($currencyCode);
        $taxCategory = $this->createTaxCategory('vat', $taxRateAmount, false);
        $variant = $this->createVariant('TB01378', $priceExcludingTax, $taxCategory);

        $order = $this->createOrder([[$variant, 1]]);

        $items = $this->buildBasket($order);
        $goods = $this->goods($items);

        self::assertCount(1, $goods);
        $this->assertLine(
            $goods[0],
            quantity: 1,
            unitPrice: $expectedAmount,
            taxRate: $expectedTaxRate,
            discount: 0.0,
            totalAmount: $expectedAmount,
        );
        $this->assertBasketLinesAreConsistent($items, $currencyCode);
        $this->assertBasketTotalMatchesOrder($items, $order);
    }

    /**
     * @return iterable<string, array{string, int, float, float, float}>
     */
    public static function currencyProvider(): iterable
    {
        yield 'no decimal (JPY): 1000 + 10 %' => ['JPY', 1000, 0.1, 1100.0, 10.0];
        yield 'two decimals (USD): 2.42 + 20 %' => ['USD', 242, 0.2, 2.9, 20.0];
        yield 'three decimals (KWD): 2.420 + 20 %' => ['KWD', 2420, 0.2, 2.904, 20.0];
    }
}
