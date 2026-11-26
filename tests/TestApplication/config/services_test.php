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

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use Tests\HiPay\SyliusHiPayPlugin\Fixture\ProductPriceFixture;

return static function (ContainerConfigurator $container): void {
    // Sample data helper for the test application fixtures (all environments).
    $container->services()
        ->set('tests.sylius_hipay_plugin.fixture.product_price', ProductPriceFixture::class)
        ->args([service('sylius.repository.product'), service('doctrine.orm.entity_manager')])
        ->tag('sylius_fixtures.fixture');

    $env = $_ENV['APP_ENV'] ?? 'dev';

    if (str_starts_with($env, 'test')) {
        $container->import('../../../vendor/sylius/sylius/src/Sylius/Behat/Resources/config/services.xml');
        $container->import('@SyliusHiPayPlugin/tests/Behat/Resources/services.xml');
    }
};
