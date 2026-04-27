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

namespace HiPay\SyliusHiPayPlugin\Provider;

use Sylius\Component\Payment\Model\PaymentMethodInterface;

class GatewayProvider
{
    public static function isHiPayGateway(?PaymentMethodInterface $paymentMethod): bool
    {
        return null !== $paymentMethod?->getGatewayConfig()?->getFactoryName() && 'hipay_hosted_fields' === $paymentMethod->getGatewayConfig()->getFactoryName();
    }
}
