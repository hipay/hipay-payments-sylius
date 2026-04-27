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

final class AccountFixture extends AbstractResourceFixture
{
    public function getName(): string
    {
        return 'hipay_account';
    }

    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $resourceNode
            ->children()
                ->scalarNode('name')->cannotBeEmpty()->end()
                ->scalarNode('code')->cannotBeEmpty()->end()
                ->scalarNode('api_username')->cannotBeEmpty()->end()
                ->scalarNode('api_password')->cannotBeEmpty()->end()
                ->scalarNode('secret_passphrase')->cannotBeEmpty()->end()
                ->scalarNode('public_username')->end()
                ->scalarNode('public_password')->end()
                ->scalarNode('test_api_username')->cannotBeEmpty()->end()
                ->scalarNode('test_api_password')->cannotBeEmpty()->end()
                ->scalarNode('test_secret_passphrase')->cannotBeEmpty()->end()
                ->scalarNode('test_public_username')->end()
                ->scalarNode('test_public_password')->end()
                ->scalarNode('environment')->defaultValue('test')->end()
                ->booleanNode('debug_mode')->defaultFalse()->end()
        ;
    }
}
