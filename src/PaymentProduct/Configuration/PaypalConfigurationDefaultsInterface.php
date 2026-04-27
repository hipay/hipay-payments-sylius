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
 * Default PayPal gateway configuration values.
 *
 * @see PaypalConfigurationType
 * @see \HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\PaypalHandler
 */
interface PaypalConfigurationDefaultsInterface
{
    public const CAN_PAY_LATER = true;

    public const BUTTON_SHAPE = 'pill';

    public const BUTTON_COLOR = 'gold';

    public const BUTTON_LABEL = 'pay';

    public const BUTTON_HEIGHT = 40;
}
