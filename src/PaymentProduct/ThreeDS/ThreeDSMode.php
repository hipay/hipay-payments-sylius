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

namespace HiPay\SyliusHiPayPlugin\PaymentProduct\ThreeDS;

/**
 * 3-D Secure mode mapping for HiPay API (authentication_indicator).
 */
final class ThreeDSMode
{
    /**
     * Bypass 3-D Secure authentication.
     * HiPay API: authentication_indicator = 0
     */
    public const DISABLED = '3ds_disabled';

    /**
     * 3-D Secure if available (recommended).
     * HiPay API: authentication_indicator = 1
     */
    public const IF_AVAILABLE = '3ds_if_available';

    /**
     * 3-D Secure mandatory.
     * HiPay API: authentication_indicator = 2
     */
    public const MANDATORY = '3ds_mandatory';

    public static function toHiPayIndicator(string $mode): int
    {
        return match ($mode) {
            self::DISABLED => 0,
            self::IF_AVAILABLE => 1,
            self::MANDATORY => 2,
            default => 1,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function getChoices(): array
    {
        return [
            'sylius_hipay_plugin.3ds.disabled' => self::DISABLED,
            'sylius_hipay_plugin.3ds.if_available' => self::IF_AVAILABLE,
            'sylius_hipay_plugin.3ds.mandatory' => self::MANDATORY,
        ];
    }
}
