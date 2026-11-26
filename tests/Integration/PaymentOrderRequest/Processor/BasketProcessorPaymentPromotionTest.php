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

use Sylius\Component\Core\Model\AdjustmentInterface;

/**
 * HIPASYLU005-8: promotions in the HiPay basket. The discount of a line is a negative amount
 * including tax, never contains the VAT, and is rounded once at the currency precision.
 */
final class BasketProcessorPaymentPromotionTest extends BasketTestCase
{
    /**
     * Acceptance case: 10.00 EUR including tax with a 10 % promotion.
     */
    public function testTaxIncludedItemWithTenPercentPromotion(): void
    {
        $taxCategory = $this->createTaxCategory('vat_20_included', 0.2, true);
        $variant = $this->createVariant('PROMO', 1000, $taxCategory);
        $this->createPromotion('ten_percent', 'order_percentage_discount', ['percentage' => 0.1]);

        $order = $this->createOrder([[$variant, 1]]);
        self::assertSame(900, $order->getTotal());

        $items = $this->buildBasket($order);
        $goods = $this->goods($items);

        self::assertCount(1, $goods);
        $this->assertLine($goods[0], quantity: 1, unitPrice: 10.0, taxRate: 20.0, discount: -1.0, totalAmount: 9.0);
        $this->assertBasketTotalMatchesOrder($items, $order);
    }

    /**
     * Item promotion applied before the VAT: 2.42 excluding tax, minus 10 % (0.24), plus VAT (0.44) = 2.62.
     * Without promotion the line is worth 2.90 including tax, so the discount including tax is 0.28
     * (and not 0.24 x 1.2 = 0.288, which would break the line total).
     */
    public function testTaxExcludedItemWithPromotionAppliedBeforeTax(): void
    {
        $taxCategory = $this->createTaxCategory('vat_20', 0.2, false);
        $variant = $this->createVariant('PROMO', 242, $taxCategory);
        $this->createPromotion(
            'ten_percent_on_units',
            'unit_percentage_discount',
            [$this->channel->getCode() => ['percentage' => 0.1]],
        );

        $order = $this->createOrder([[$variant, 1]]);
        self::assertSame(262, $order->getTotal());

        $items = $this->buildBasket($order);
        $goods = $this->goods($items);

        self::assertCount(1, $goods);
        $this->assertLine($goods[0], quantity: 1, unitPrice: 2.9, taxRate: 20.0, discount: -0.28, totalAmount: 2.62);
        $this->assertBasketTotalMatchesOrder($items, $order);
    }

    /**
     * Ticket example: 8.33 EUR excluding tax (10.00 including tax) with a 10 % promotion.
     * Sylius discounts 0.83 excluding tax; the basket must carry 1.00 including tax.
     */
    public function testTaxExcludedPromotionIsConvertedToATaxIncludedDiscount(): void
    {
        $taxCategory = $this->createTaxCategory('vat_20', 0.2, false);
        $variant = $this->createVariant('PROMO', 833, $taxCategory);
        $this->createPromotion('ten_percent', 'order_percentage_discount', ['percentage' => 0.1]);

        $order = $this->createOrder([[$variant, 1]]);
        self::assertSame(
            -83,
            $order->getAdjustmentsTotalRecursively(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT),
            'Sylius is expected to compute the promotion excluding tax.',
        );
        self::assertSame(900, $order->getTotal());

        $items = $this->buildBasket($order);
        $goods = $this->goods($items);

        self::assertCount(1, $goods);
        $this->assertLine($goods[0], quantity: 1, unitPrice: 10.0, taxRate: 20.0, discount: -1.0, totalAmount: 9.0);
        $this->assertBasketTotalMatchesOrder($items, $order);
    }

    /**
     * No amount such as 0.9996 (rounded tax-excluded discount x 1.2) may reach the basket: every
     * amount stays at the currency precision and the line still adds up to the Sylius total.
     *
     * @dataProvider taxExcludedPromotionProvider
     */
    public function testNoIntermediateRoundingLeaksIntoTheBasket(
        int $priceExcludingTax,
        float $percentage,
        float $expectedUnitPrice,
    ): void {
        $taxCategory = $this->createTaxCategory('vat_20', 0.2, false);
        $variant = $this->createVariant('PROMO', $priceExcludingTax, $taxCategory);
        $this->createPromotion('percentage', 'order_percentage_discount', ['percentage' => $percentage]);

        $order = $this->createOrder([[$variant, 1]]);

        $items = $this->buildBasket($order);
        $goods = $this->goods($items);

        self::assertCount(1, $goods);
        self::assertEquals($expectedUnitPrice, $goods[0]->getUnitPrice(), 'unit_price is the tax-included price before promotion.');
        self::assertLessThan(0.0, (float) $goods[0]->getDiscount());
        $this->assertBasketLinesAreConsistent($items, 'EUR');
        $this->assertBasketTotalMatchesOrder($items, $order);
    }

    /**
     * @return iterable<string, array{int, float, float}>
     */
    public static function taxExcludedPromotionProvider(): iterable
    {
        yield '8.33 excluding tax, 10 % (0.83 x 1.2 = 0.996)' => [833, 0.1, 10.0];
        yield '2.42 excluding tax, 15 %' => [242, 0.15, 2.9];
        yield '19.99 excluding tax, 33 %' => [1999, 0.33, 23.99];
    }

    /**
     * Item offered by a 100 % promotion: Sylius computes no tax, so no tax adjustment carries
     * the rate. The line must still show the price including tax (12.00, not 10.00) and the
     * real rate, both read from the Sylius tax configuration.
     */
    public function testFullyDiscountedTaxExcludedItemKeepsItsTaxIncludedPriceAndRate(): void
    {
        $taxCategory = $this->createTaxCategory('vat_20', 0.2, false);
        $variant = $this->createVariant('PROMO', 1000, $taxCategory);
        $this->createPromotion('offered', 'order_percentage_discount', ['percentage' => 1.0]);

        $order = $this->createOrder([[$variant, 1]]);
        self::assertSame(0, $order->getTotal());
        self::assertSame(0, $order->getTaxTotal(), 'Sylius is expected to set no tax on a free item.');

        $items = $this->buildBasket($order);
        $goods = $this->goods($items);

        self::assertCount(1, $goods);
        $this->assertLine($goods[0], quantity: 1, unitPrice: 12.0, taxRate: 20.0, discount: -12.0, totalAmount: 0.0);
        $this->assertBasketTotalMatchesOrder($items, $order);
    }

    /**
     * A cart promotion (10.00 EUR off) is distributed by Sylius on the 3 units (3.34 / 3.33 / 3.33
     * excluding tax). It is carried by the product line, so no separate discount line may repeat it.
     */
    public function testCartPromotionDistributedOverSeveralUnits(): void
    {
        $taxCategory = $this->createTaxCategory('vat_20', 0.2, false);
        $variant = $this->createVariant('PROMO', 1000, $taxCategory);
        $this->createPromotion(
            'ten_euros_off',
            'order_fixed_discount',
            [$this->channel->getCode() => ['amount' => 1000]],
        );

        $order = $this->createOrder([[$variant, 3]]);
        self::assertSame(2400, $order->getTotal());

        $items = $this->buildBasket($order);
        $goods = $this->goods($items);

        self::assertCount(1, $goods);
        $this->assertLine($goods[0], quantity: 3, unitPrice: 12.0, taxRate: 20.0, discount: -12.0, totalAmount: 24.0);
        self::assertCount(0, $this->discounts($items), 'A promotion distributed on the units must not be sent twice.');
        $this->assertBasketTotalMatchesOrder($items, $order);
    }

    /**
     * An order-level promotion adjustment (not distributed on the units) is sent as a dedicated
     * discount line and must not be injected again into the product line.
     */
    public function testCartPromotionSentAsADiscountLine(): void
    {
        $taxCategory = $this->createTaxCategory('vat_20', 0.2, false);
        $variant = $this->createVariant('PROMO', 1000, $taxCategory);

        $order = $this->createOrder([[$variant, 1]]);

        /** @var AdjustmentInterface $adjustment */
        $adjustment = self::getContainer()->get('sylius.factory.adjustment')->createNew();
        $adjustment->setType(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT);
        $adjustment->setLabel('Commercial gesture');
        $adjustment->setAmount(-500);
        $order->addAdjustment($adjustment);
        self::assertSame(700, $order->getTotal());

        $items = $this->buildBasket($order);
        $goods = $this->goods($items);
        $discounts = $this->discounts($items);

        self::assertCount(1, $goods);
        $this->assertLine($goods[0], quantity: 1, unitPrice: 12.0, taxRate: 20.0, discount: 0.0, totalAmount: 12.0);
        self::assertCount(1, $discounts);
        self::assertEquals(-5.0, $discounts[0]->getTotalAmount());
        $this->assertBasketTotalMatchesOrder($items, $order);
    }
}
