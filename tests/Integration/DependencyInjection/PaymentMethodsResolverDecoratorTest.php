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

namespace Tests\HiPay\SyliusHiPayPlugin\Integration\DependencyInjection;

use HiPay\SyliusHiPayPlugin\Resolver\PaymentMethodsResolver;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Ensures sylius.resolver.payment_methods.channel_based is decorated by the plugin resolver
 * (config/services.php decorator registration).
 */
final class PaymentMethodsResolverDecoratorTest extends KernelTestCase
{
    public function testChannelBasedServiceIsDecoratedWithPluginResolver(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->assertTrue(
            $container->has('sylius.resolver.payment_methods.channel_based'),
            'Channel-based payment methods resolver should be registered.',
        );

        $resolver = $container->get('sylius.resolver.payment_methods.channel_based');

        $this->assertInstanceOf(
            PaymentMethodsResolver::class,
            $resolver,
            'sylius.resolver.payment_methods.channel_based should be decorated by HiPay PaymentMethodsResolver.',
        );
    }

    public function testDecoratorServiceIdIsRegistered(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->assertTrue(
            $container->has('sylius_hipay_plugin.resolver.payment_methods.channel_based_decorator'),
            'Decorator service id should exist (may be private; still registered).',
        );
    }
}
