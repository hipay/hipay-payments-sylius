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

namespace HiPay\SyliusHiPayPlugin\PaymentProduct\Capture;

/**
 * HiPay `operation` value for an order request. The capture mode is stored at
 * the gateway-config root as the boolean `use_authorize` (read natively by
 * Sylius DefaultActionProvider to decide authorize vs capture); this enum maps
 * that flag to the HiPay `operation` value.
 */
enum CaptureMode: string
{
    case INSTANT = 'Sale';
    case ON_DEMAND = 'Authorization';

    public static function operationFromUseAuthorize(bool $useAuthorize): string
    {
        return ($useAuthorize ? self::ON_DEMAND : self::INSTANT)->value;
    }

    /**
     * Select choices for the "Mode de capture" field, mapped to the
     * `use_authorize` boolean (immediate = false, delayed = true).
     *
     * @return array<string, bool>
     */
    public static function getChoices(): array
    {
        return [
            'sylius_hipay_plugin.capture_mode.instant' => false,
            'sylius_hipay_plugin.capture_mode.on_demand' => true,
        ];
    }
}
