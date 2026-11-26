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

use Sylius\Component\Core\Model\OrderInterface;

/**
 * HIPASYLU005-8: arithmetic contract of the HiPay basket, checked on representative carts.
 */
final class BasketProcessorPaymentConsistencyTest extends BasketTestCase
{
    private const SCENARIO_TAX_EXCLUDED = 'tax excluded item';

    private const SCENARIO_TAX_INCLUDED = 'tax included item';

    private const SCENARIO_ROUNDING_REMAINDER = 'tax excluded quantity with rounding remainder';

    private const SCENARIO_MIXED_CART = 'mixed cart with promotions and taxed shipping';

    /**
     * @dataProvider scenarioProvider
     */
    public function testSumOfTotalAmountsEqualsTheOrderTotal(string $scenario): void
    {
        $order = $this->createScenarioOrder($scenario);

        $this->assertBasketTotalMatchesOrder($this->buildBasket($order), $order);
    }

    /**
     * HiPay rejects any line with a positive discount
     * ("The format of discount field is invalid. Please respect the format negative.").
     *
     * @dataProvider scenarioProvider
     */
    public function testDiscountsAreNeverPositive(string $scenario): void
    {
        $order = $this->createScenarioOrder($scenario);

        foreach ($this->buildBasket($order) as $item) {
            self::assertLessThanOrEqual(
                0.0,
                (float) $item->getDiscount(),
                sprintf('Basket line "%s" carries a positive discount.', $item->getProductReference()),
            );
        }
    }

    /**
     * @dataProvider scenarioProvider
     */
    public function testEachLineRespectsTheBasketArithmetic(string $scenario): void
    {
        $order = $this->createScenarioOrder($scenario);

        $this->assertBasketLinesAreConsistent($this->buildBasket($order), (string) $order->getCurrencyCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function scenarioProvider(): iterable
    {
        yield self::SCENARIO_TAX_EXCLUDED => [self::SCENARIO_TAX_EXCLUDED];
        yield self::SCENARIO_TAX_INCLUDED => [self::SCENARIO_TAX_INCLUDED];
        yield self::SCENARIO_ROUNDING_REMAINDER => [self::SCENARIO_ROUNDING_REMAINDER];
        yield self::SCENARIO_MIXED_CART => [self::SCENARIO_MIXED_CART];
    }

    private function createScenarioOrder(string $scenario): OrderInterface
    {
        return match ($scenario) {
            self::SCENARIO_TAX_EXCLUDED => $this->createOrder([
                [$this->createVariant('TB01378', 242, $this->createTaxCategory('vat_20', 0.2, false)), 1],
            ]),
            self::SCENARIO_TAX_INCLUDED => $this->createOrder([
                [$this->createVariant('TB01378', 290, $this->createTaxCategory('vat_20', 0.2, true)), 1],
            ]),
            self::SCENARIO_ROUNDING_REMAINDER => $this->createOrder([
                [$this->createVariant('TB01378', 242, $this->createTaxCategory('vat_20', 0.2, false)), 3],
            ]),
            self::SCENARIO_MIXED_CART => $this->createMixedCartOrder(),
        };
    }

    /**
     * Two tax rates, quantities above 1, a 10 % cart promotion, taxed shipping and 50 % off the shipping.
     */
    private function createMixedCartOrder(): OrderInterface
    {
        $standardTax = $this->createTaxCategory('vat_20', 0.2, false);
        $reducedTax = $this->createTaxCategory('vat_5_5', 0.055, false);
        $this->createShippingMethod(490, $standardTax);
        $this->createPromotion('ten_percent', 'order_percentage_discount', ['percentage' => 0.1]);
        $this->createPromotion('half_shipping', 'shipping_percentage_discount', ['percentage' => 0.5]);

        return $this->createOrder([
            [$this->createVariant('STANDARD', 1999, $standardTax, shippingRequired: true), 2],
            [$this->createVariant('REDUCED', 242, $reducedTax, shippingRequired: true), 3],
        ]);
    }
}
