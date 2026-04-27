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

/**
 * Default Apple Pay gateway configuration values.
 *
 * @see ApplePayConfigurationType
 * @see \HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\ApplePayHandler
 */
interface ApplePayConfigurationDefaultsInterface
{
    /** @var list<string> */
    public const SUPPORTED_NETWORKS = ['visa', 'masterCard', 'cartesBancaires', 'maestro'];

    public const BUTTON_TYPE = 'buy';

    public const BUTTON_COLOR = 'black';
}
