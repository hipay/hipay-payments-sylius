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

namespace HiPay\SyliusHiPayPlugin\PaymentProduct\Oney;

/** Oney payment delivery method identifiers (official varchar codes for merchant integrations). */
enum OneyStandardShippingMethod: string
{
    // STORE
    case StoreStandard = 'store_standard';
    case StoreExpress = 'store_express';
    case StorePriority24h = 'store_priority24h';
    case StorePriority2h = 'store_priority2h';
    case StorePriority1h = 'store_priority1h';
    case StoreInstant = 'store_instant';

    // CARRIER
    case CarrierStandard = 'carrier_standard';
    case CarrierExpress = 'carrier_express';
    case CarrierPriority24h = 'carrier_priority24h';
    case CarrierPriority2h = 'carrier_priority2h';
    case CarrierPriority1h = 'carrier_priority1h';
    case CarrierInstant = 'carrier_instant';

    // RELAYPOINT
    case RelaypointStandard = 'relaypoint_standard';
    case RelaypointExpress = 'relaypoint_express';
    case RelaypointPriority24h = 'relaypoint_priority24h';
    case RelaypointPriority2h = 'relaypoint_priority2h';
    case RelaypointPriority1h = 'relaypoint_priority1h';
    case RelaypointInstant = 'relaypoint_instant';

    // ELECTRONIC
    case ElectronicStandard = 'electronic_standard';
    case ElectronicExpress = 'electronic_express';
    case ElectronicPriority24h = 'electronic_priority24h';
    case ElectronicPriority2h = 'electronic_priority2h';
    case ElectronicPriority1h = 'electronic_priority1h';
    case ElectronicInstant = 'electronic_instant';

    // TRAVEL
    case TravelStandard = 'travel_standard';
    case TravelExpress = 'travel_express';
    case TravelPriority24h = 'travel_priority24h';
    case TravelPriority1h = 'travel_priority1h';
    case TravelInstant = 'travel_instant';

    /** Translation key for admin forms and grid filters (avoids closures in Symfony PHP config). */
    public static function choiceTranslationKey(self $case): string
    {
        return sprintf('sylius_hipay_plugin.oney_standard_shipping_method.%s', $case->value);
    }

    /**
     * @return array{mode: string, shipping: string}
     */
    public function toArray(): array
    {
        $data = explode('_', $this->value);

        return [
            'mode' => strtoupper($data[0] ?? ''),
            'shipping' => strtoupper($data[1] ?? ''),
        ];
    }
}
