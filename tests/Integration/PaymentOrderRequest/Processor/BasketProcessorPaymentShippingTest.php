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

use HiPay\Fullservice\Gateway\Model\Cart\Item;
use Sylius\Component\Core\Model\OrderInterface;

/**
 * HIPASYLU005-8: shipping costs are sent as fee lines including tax, with their real
 * tax rate and the shipping promotion as a negative discount.
 */
final class BasketProcessorPaymentShippingTest extends BasketTestCase
{
    /**
     * 5.00 EUR excluding tax of shipping, 20 % VAT: 6.00 including tax.
     */
    public function testTaxedShipping(): void
    {
        $order = $this->createOrderWithShipping(shippingAmount: 500);
        self::assertSame(600, $order->getShippingTotal());

        $items = $this->buildBasket($order);
        $fees = $this->fees($items);

        self::assertCount(1, $fees);
        $this->assertLine($fees[0], quantity: 1, unitPrice: 6.0, taxRate: 20.0, discount: 0.0, totalAmount: 6.0);
        $this->assertBasketTotalMatchesOrder($items, $order);
    }

    /**
     * 50 % off the shipping: 2.50 excluding tax + 0.50 VAT = 3.00, i.e. a 3.00 discount including tax.
     */
    public function testShippingWithPromotion(): void
    {
        $this->createPromotion('half_shipping', 'shipping_percentage_discount', ['percentage' => 0.5]);

        $order = $this->createOrderWithShipping(shippingAmount: 500);
        self::assertSame(300, $order->getShippingTotal());

        $items = $this->buildBasket($order);
        $fees = $this->fees($items);

        self::assertCount(1, $fees);
        $this->assertLine($fees[0], quantity: 1, unitPrice: 6.0, taxRate: 20.0, discount: -3.0, totalAmount: 3.0);
        $this->assertBasketTotalMatchesOrder($items, $order);
    }

    /**
     * Free shipping, either with a free method or a 100 % shipping promotion: whatever the
     * representation (no fee line, or a fee line fully discounted), nothing is charged for it.
     *
     * @dataProvider freeShippingProvider
     */
    public function testFreeShipping(int $shippingAmount, bool $withFreeShippingPromotion): void
    {
        if ($withFreeShippingPromotion) {
            $this->createPromotion('free_shipping', 'shipping_percentage_discount', ['percentage' => 1.0]);
        }

        $order = $this->createOrderWithShipping($shippingAmount);
        self::assertSame(0, $order->getShippingTotal());

        $items = $this->buildBasket($order);

        self::assertEquals(
            0.0,
            array_sum(array_map(static fn (Item $item): float => (float) $item->getTotalAmount(), $this->fees($items))),
        );
        $this->assertBasketLinesAreConsistent($items, 'EUR');
        $this->assertBasketTotalMatchesOrder($items, $order);
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function freeShippingProvider(): iterable
    {
        yield 'free shipping method' => [0, false];
        yield '100 % shipping promotion' => [500, true];
    }

    private function createOrderWithShipping(int $shippingAmount): OrderInterface
    {
        $taxCategory = $this->createTaxCategory('vat_20', 0.2, false);
        $variant = $this->createVariant('SHIPPED', 1000, $taxCategory, shippingRequired: true);
        $this->createShippingMethod($shippingAmount, $taxCategory);

        $order = $this->createOrder([[$variant, 1]]);
        self::assertCount(1, $order->getShipments(), 'The order is expected to have a shipment.');

        return $order;
    }
}
