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

namespace HiPay\SyliusHiPayPlugin\RefundPlugin\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AddRefundGatewayPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $parameterName = 'sylius_refund.supported_gateways';
        if (!$container->hasParameter($parameterName)) {
            return;
        }
        /** @var array $gateways */
        $gateways = $container->getParameter($parameterName);
        $gateways[] = 'hipay_hosted_fields';

        $container->setParameter($parameterName, array_unique($gateways));
    }
}
