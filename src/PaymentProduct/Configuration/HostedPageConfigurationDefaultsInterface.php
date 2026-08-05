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
 * Default Hosted page gateway configuration values.
 *
 * @see HostedPageConfigurationType
 */
interface HostedPageConfigurationDefaultsInterface
{
    public const TEMPLATE = 'basic-js';

    public const TIME_LIMIT_TO_PAY = 1800;
}
