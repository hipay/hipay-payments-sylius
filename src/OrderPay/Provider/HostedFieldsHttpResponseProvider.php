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

namespace HiPay\SyliusHiPayPlugin\OrderPay\Provider;

/**
 * After-pay HTTP response provider for the HiPay Hosted Fields gateway.
 *
 * All behaviour is shared with the Hosted Page provider via
 * {@see AbstractHostedHttpResponseProvider}.
 */
final class HostedFieldsHttpResponseProvider extends AbstractHostedHttpResponseProvider
{
}
