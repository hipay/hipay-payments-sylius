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

namespace HiPay\SyliusHiPayPlugin\Util;

use Symfony\Component\Intl\Currencies;
use Symfony\Component\Intl\Exception\MissingResourceException;

/**
 * Converts Sylius amounts (integers in the smallest currency unit) to the major
 * currency unit expected by the HiPay API, respecting ISO 4217 decimal places.
 *
 * Delegates decimal-place lookup to symfony/intl so the data stays up to date
 * without manual maintenance. Falls back to 2 for unknown currency codes.
 */
final class CurrencyAmount
{
    /**
     * Returns the number of fraction digits (decimal places) for a given ISO 4217 currency code.
     * Delegates to symfony/intl; defaults to 2 for unknown currencies.
     */
    public static function fractionDigits(string $currencyCode): int
    {
        try {
            return Currencies::getFractionDigits($currencyCode);
        } catch (MissingResourceException) {
            return 2;
        }
    }

    /**
     * Converts an amount from the smallest currency unit (as stored by Sylius)
     * to the major unit expected by the HiPay API.
     *
     * Examples:
     *   - EUR 1000 cents      → 10.00  (fractionDigits=2, divide by 100)
     *   - JPY 1000 yen        → 1000   (fractionDigits=0, no division)
     *   - KWD 1000 fils       → 1.000  (fractionDigits=3, divide by 1000)
     */
    public static function fromSmallestUnit(int $amount, string $currencyCode): float
    {
        $digits = self::fractionDigits($currencyCode);

        if (0 === $digits) {
            return (float) $amount;
        }

        return round($amount / (10 ** $digits), $digits);
    }

    /**
     * Converts an amount from the smallest currency unit to a string formatted with
     * the correct number of decimal places for the HiPay API.
     *
     * PHP's default float-to-string conversion drops trailing zeros (e.g. 154979.0 → "154979"),
     * which causes HIPAY_CREATE_INVALID_OPTION_VALUE errors for currencies like OMR (3 decimals).
     *
     * Examples:
     *   - EUR 1000 cents  → "10.00"
     *   - JPY 1000 yen    → "1000"
     *   - OMR 154979000   → "154979.000"
     */
    public static function formatFromSmallestUnit(int $amount, string $currencyCode): string
    {
        $digits = self::fractionDigits($currencyCode);
        $value = self::fromSmallestUnit($amount, $currencyCode);

        return number_format($value, $digits, '.', '');
    }
}
