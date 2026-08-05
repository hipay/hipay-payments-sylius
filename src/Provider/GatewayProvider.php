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
    public const FACTORY_HOSTED_FIELDS = 'hipay_hosted_fields';

    public const FACTORY_HOSTED_PAGE = 'hipay_hosted_page';

    private const HIPAY_GATEWAY_FACTORIES = [
        self::FACTORY_HOSTED_FIELDS,
        self::FACTORY_HOSTED_PAGE,
    ];

    public static function isHiPayGateway(?PaymentMethodInterface $paymentMethod): bool
    {
        $factoryName = $paymentMethod?->getGatewayConfig()?->getFactoryName();

        return null !== $factoryName && in_array($factoryName, self::HIPAY_GATEWAY_FACTORIES, true);
    }
}
