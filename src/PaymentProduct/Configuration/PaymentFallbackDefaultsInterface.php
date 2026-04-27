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

namespace HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration;

interface PaymentFallbackDefaultsInterface
{
    public const COUNTRY_CODE = 'FR';

    public const LANGUAGE_CODE = 'fr_FR';

    public const CURRENCY_CODE = 'EUR';
}
