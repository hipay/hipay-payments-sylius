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

namespace HiPay\SyliusHiPayPlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

final class OneyShippingMethodFixture extends AbstractResourceFixture
{
    public function getName(): string
    {
        return 'hipay_oney_shipping_method';
    }

    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $resourceNode
            ->children()
                ->scalarNode('shipping_method')->cannotBeEmpty()->end()
                ->scalarNode('oney_shipping_method')->cannotBeEmpty()->end()
                ->integerNode('oney_preparation_time')->min(0)->defaultValue(0)->end()
                ->integerNode('oney_delivery_time')->min(0)->defaultValue(0)->end()
        ;
    }
}
