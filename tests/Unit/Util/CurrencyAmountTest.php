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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Util;

use HiPay\SyliusHiPayPlugin\Util\CurrencyAmount;
use PHPUnit\Framework\TestCase;

final class CurrencyAmountTest extends TestCase
{
    // ── fractionDigits ───────────────────────────────────────────────────────

    public function testFractionDigitsDefaultsToTwo(): void
    {
        $this->assertSame(2, CurrencyAmount::fractionDigits('EUR'));
        $this->assertSame(2, CurrencyAmount::fractionDigits('USD'));
        $this->assertSame(2, CurrencyAmount::fractionDigits('GBP'));
        $this->assertSame(2, CurrencyAmount::fractionDigits('CHF'));
    }

    public function testFractionDigitsZeroDecimalCurrencies(): void
    {
        foreach (['JPY', 'KRW', 'XAF', 'XOF', 'XPF', 'CLP', 'VND'] as $code) {
            $this->assertSame(0, CurrencyAmount::fractionDigits($code), "Expected 0 for {$code}");
        }
    }

    public function testFractionDigitsThreeDecimalCurrencies(): void
    {
        foreach (['KWD', 'BHD', 'OMR', 'TND'] as $code) {
            $this->assertSame(3, CurrencyAmount::fractionDigits($code), "Expected 3 for {$code}");
        }
    }

    public function testFractionDigitsUnknownCurrencyDefaultsToTwo(): void
    {
        $this->assertSame(2, CurrencyAmount::fractionDigits('XXX'));
    }

    // ── fromSmallestUnit – standard 2-decimal currencies ────────────────────

    public function testFromSmallestUnitEur(): void
    {
        $this->assertSame(10.0, CurrencyAmount::fromSmallestUnit(1000, 'EUR'));
        $this->assertSame(0.01, CurrencyAmount::fromSmallestUnit(1, 'EUR'));
        $this->assertSame(99.99, CurrencyAmount::fromSmallestUnit(9999, 'EUR'));
    }

    public function testFromSmallestUnitUsd(): void
    {
        $this->assertSame(1.99, CurrencyAmount::fromSmallestUnit(199, 'USD'));
    }

    // ── fromSmallestUnit – 0-decimal currencies ──────────────────────────────

    public function testFromSmallestUnitJpy(): void
    {
        $this->assertSame(1000.0, CurrencyAmount::fromSmallestUnit(1000, 'JPY'));
        $this->assertSame(1.0, CurrencyAmount::fromSmallestUnit(1, 'JPY'));
    }

    public function testFromSmallestUnitKrw(): void
    {
        $this->assertSame(50000.0, CurrencyAmount::fromSmallestUnit(50000, 'KRW'));
    }

    public function testFromSmallestUnitXaf(): void
    {
        $this->assertSame(5000.0, CurrencyAmount::fromSmallestUnit(5000, 'XAF'));
    }

    // ── fromSmallestUnit – 3-decimal currencies ──────────────────────────────

    public function testFromSmallestUnitKwd(): void
    {
        $this->assertSame(10.0, CurrencyAmount::fromSmallestUnit(10000, 'KWD'));
        $this->assertSame(0.001, CurrencyAmount::fromSmallestUnit(1, 'KWD'));
        $this->assertSame(1.234, CurrencyAmount::fromSmallestUnit(1234, 'KWD'));
    }

    public function testFromSmallestUnitBhd(): void
    {
        $this->assertSame(5.5, CurrencyAmount::fromSmallestUnit(5500, 'BHD'));
    }

    public function testFromSmallestUnitOmr(): void
    {
        $this->assertSame(99.999, CurrencyAmount::fromSmallestUnit(99999, 'OMR'));
    }

    // ── Rounding with percentage discounts ──────────────────────────────────

    /**
     * A 10% discount on a 3-item basket where each item costs 333 cents (3.33€):
     * total before discount = 999 cents, discount = -99 cents (floor), total = 900 cents.
     * HiPay amount must equal the sum of basket line amounts.
     */
    public function testAmountEqualityWithPercentageDiscount(): void
    {
        $unitPriceCents = 333;
        $quantity = 3;
        $subtotalCents = $unitPriceCents * $quantity; // 999
        $discountCents = (int) round($subtotalCents * -0.10); // -99 (rounded to nearest cent)
        $totalCents = $subtotalCents + $discountCents; // 900

        $currency = 'EUR';

        $unitPriceConverted = CurrencyAmount::fromSmallestUnit($unitPriceCents, $currency);
        $discountConverted = CurrencyAmount::fromSmallestUnit($discountCents, $currency);

        // Basket total = (unit_price * qty) + discount
        $basketTotal = round($unitPriceConverted * $quantity + $discountConverted, 2);
        $orderAmount = CurrencyAmount::fromSmallestUnit($totalCents, $currency);

        $this->assertSame($orderAmount, $basketTotal);
    }

    /**
     * Same scenario in JPY (0 decimal): discount is exact, no floating-point risk.
     */
    public function testAmountEqualityWithPercentageDiscountJpy(): void
    {
        $unitPriceCents = 333;
        $quantity = 3;
        $subtotal = $unitPriceCents * $quantity; // 999
        $discount = (int) round($subtotal * -0.10); // -99 or -100
        $total = $subtotal + $discount;

        $currency = 'JPY';

        $unitConverted = CurrencyAmount::fromSmallestUnit($unitPriceCents, $currency);
        $discountConverted = CurrencyAmount::fromSmallestUnit($discount, $currency);

        $basketTotal = $unitConverted * $quantity + $discountConverted;
        $orderAmount = CurrencyAmount::fromSmallestUnit($total, $currency);

        $this->assertSame($orderAmount, $basketTotal);
    }

    /**
     * KWD (3 decimal): 10% off a 3-item basket at 3.333 KWD each (3333 fils).
     * total = 10000 – 1000 = 9000 fils → 9.000 KWD.
     */
    public function testAmountEqualityWithPercentageDiscountKwd(): void
    {
        $unitPriceFils = 3333;
        $quantity = 3;
        $subtotal = $unitPriceFils * $quantity; // 9999
        $discount = (int) round($subtotal * -0.10); // -1000
        $total = $subtotal + $discount; // 8999

        $currency = 'KWD';

        $unitConverted = CurrencyAmount::fromSmallestUnit($unitPriceFils, $currency);
        $discountConverted = CurrencyAmount::fromSmallestUnit($discount, $currency);

        $basketTotal = round($unitConverted * $quantity + $discountConverted, 3);
        $orderAmount = CurrencyAmount::fromSmallestUnit($total, $currency);

        $this->assertSame($orderAmount, $basketTotal);
    }

    // ── Zero amount edge case ────────────────────────────────────────────────

    public function testFromSmallestUnitZeroAmount(): void
    {
        $this->assertSame(0.0, CurrencyAmount::fromSmallestUnit(0, 'EUR'));
        $this->assertSame(0.0, CurrencyAmount::fromSmallestUnit(0, 'JPY'));
        $this->assertSame(0.0, CurrencyAmount::fromSmallestUnit(0, 'KWD'));
    }

    // ── formatFromSmallestUnit – preserves trailing zeros as string ──────────

    /** 0-decimal currencies (JPY, KRW, XAF): no decimal point in output */
    public function testFormatFromSmallestUnitZeroDecimalCurrency(): void
    {
        $this->assertSame('1000', CurrencyAmount::formatFromSmallestUnit(1000, 'JPY'));
        $this->assertSame('1', CurrencyAmount::formatFromSmallestUnit(1, 'JPY'));
        $this->assertSame('50000', CurrencyAmount::formatFromSmallestUnit(50000, 'KRW'));
        $this->assertSame('5000', CurrencyAmount::formatFromSmallestUnit(5000, 'XAF'));
        $this->assertSame('0', CurrencyAmount::formatFromSmallestUnit(0, 'JPY'));
    }

    /** 2-decimal currencies (EUR, USD): trailing zero preserved (e.g. "10.00" not "10") */
    public function testFormatFromSmallestUnitTwoDecimalCurrency(): void
    {
        $this->assertSame('10.00', CurrencyAmount::formatFromSmallestUnit(1000, 'EUR'));
        $this->assertSame('0.01', CurrencyAmount::formatFromSmallestUnit(1, 'EUR'));
        $this->assertSame('99.99', CurrencyAmount::formatFromSmallestUnit(9999, 'EUR'));
        $this->assertSame('1.99', CurrencyAmount::formatFromSmallestUnit(199, 'USD'));
        $this->assertSame('0.00', CurrencyAmount::formatFromSmallestUnit(0, 'EUR'));
    }

    /** 3-decimal currencies (OMR, KWD, BHD): trailing zeros must be preserved */
    public function testFormatFromSmallestUnitThreeDecimalCurrency(): void
    {
        $this->assertSame('154979.000', CurrencyAmount::formatFromSmallestUnit(154979000, 'OMR'));
        $this->assertSame('10.000', CurrencyAmount::formatFromSmallestUnit(10000, 'KWD'));
        $this->assertSame('0.001', CurrencyAmount::formatFromSmallestUnit(1, 'KWD'));
        $this->assertSame('1.234', CurrencyAmount::formatFromSmallestUnit(1234, 'KWD'));
        $this->assertSame('5.500', CurrencyAmount::formatFromSmallestUnit(5500, 'BHD'));
        $this->assertSame('99.999', CurrencyAmount::formatFromSmallestUnit(99999, 'OMR'));
        $this->assertSame('0.000', CurrencyAmount::formatFromSmallestUnit(0, 'OMR'));
    }
}
