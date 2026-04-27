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

use HiPay\SyliusHiPayPlugin\Client\ClientProvider;
use HiPay\SyliusHiPayPlugin\Client\ClientProviderInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestBuilderRegistry;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestBuilderRegistryInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContextFactory;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContextFactoryInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerRegistry;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerRegistryInterface;
use HiPay\SyliusHiPayPlugin\Provider\AccountProvider;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\OneyCategoryProvider;
use HiPay\SyliusHiPayPlugin\Provider\OneyCategoryProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\OneyShippingMethodProvider;
use HiPay\SyliusHiPayPlugin\Provider\OneyShippingMethodProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\PaymentProductProvider;
use HiPay\SyliusHiPayPlugin\Provider\PaymentProductProviderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ServicesAvailabilityTest extends KernelTestCase
{
    /**
     * @dataProvider publicServiceIdProvider
     */
    public function testServiceIsRegisteredAndInstantiable(string $serviceId): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->assertTrue(
            $container->has($serviceId),
            sprintf('Service "%s" should be registered in the container.', $serviceId),
        );

        $service = $container->get($serviceId);
        $this->assertNotNull($service, sprintf('Service "%s" should be instantiable.', $serviceId));
    }

    /**
     * @dataProvider interfaceAliasProvider
     */
    public function testInterfaceAliasResolvesToConcreteClass(string $interface, string $expectedClass): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $service = $container->get($interface);

        $this->assertInstanceOf($expectedClass, $service);
    }

    /**
     * Services that are correctly wired and can be instantiated from the test container.
     *
     * @return iterable<string, array{string}>
     */
    public static function publicServiceIdProvider(): iterable
    {
        yield 'hipay client provider' => ['sylius_hipay_plugin.client.provider'];
        yield 'account provider' => ['sylius_hipay_plugin.provider.account'];
        yield 'oney category provider' => ['sylius_hipay_plugin.provider.oney_category'];
        yield 'oney shipping method provider' => ['sylius_hipay_plugin.provider.oney_shipping_method'];
        yield 'payment product provider' => ['sylius_hipay_plugin.provider.payment_product'];
        yield 'hipay hosted fields command provider' => ['sylius_hipay_plugin.command_provider.hipay_hosted_fields'];
        yield 'hipay hosted fields http response provider' => ['sylius_hipay_plugin.http_response_provider.hipay_hosted_fields'];
        yield 'account form type' => ['sylius_hipay_plugin.form.type.resource_account'];
        yield 'oney category form type' => ['sylius_hipay_plugin.form.type.resource_oney_category'];
        yield 'oney shipping method form type' => ['sylius_hipay_plugin.form.type.resource_oney_shipping_method'];
        yield 'admin menu listener' => ['sylius_hipay_plugin.event_listener.admin.menu'];
        yield 'account fixture factory' => ['sylius_hipay_plugin.fixture.factory.account'];
        yield 'account fixture' => ['sylius_hipay_plugin.fixture.account'];
        yield 'oney category fixture factory' => ['sylius_hipay_plugin.fixture.factory.oney_category'];
        yield 'oney category fixture' => ['sylius_hipay_plugin.fixture.oney_category'];
        yield 'oney shipping method fixture factory' => ['sylius_hipay_plugin.fixture.factory.oney_shipping_method'];
        yield 'oney shipping method fixture' => ['sylius_hipay_plugin.fixture.oney_shipping_method'];
        yield 'payment product handler registry' => ['sylius_hipay_plugin.payment_product.handler_registry'];
        yield 'payment product handler card' => ['sylius_hipay_plugin.payment_product.handler.card'];
        yield 'payment product handler oney' => ['sylius_hipay_plugin.payment_product.handler.oney'];
        yield 'hosted fields form type' => ['sylius_hipay_plugin.form.type.hosted_fields_gateway_configuration'];
        yield 'hosted fields live component' => ['sylius_hipay_plugin.twig.component.shop.hosted_fields'];
        yield 'payment order request builder registry' => ['sylius_hipay_plugin.payment_order_request.order_request_registry'];
        yield 'payment order request context factory' => ['sylius_hipay_plugin.payment_order_request.context_factory'];
        yield 'payment order request card builder' => ['sylius_hipay_plugin.payment_order_request.order_request.card'];
        yield 'payment order request oney builder' => ['sylius_hipay_plugin.payment_order_request.order_request.oney'];
        yield 'payment order request processor common fields' => ['sylius_hipay_plugin.payment_order_request.processor.common_fields'];
        yield 'payment order request processor customer billing' => ['sylius_hipay_plugin.payment_order_request.processor.customer_billing'];
        yield 'payment order request processor browser info' => ['sylius_hipay_plugin.payment_order_request.processor.browser_info'];
        yield 'payment order request processor callback urls' => ['sylius_hipay_plugin.payment_order_request.processor.callback_urls'];
        yield 'payment order request processor card payment method' => ['sylius_hipay_plugin.payment_order_request.processor.card_payment_method'];
        yield 'payment order request processor oney payment method' => ['sylius_hipay_plugin.payment_order_request.processor.oney_payment_method'];
        yield 'new order request message handler' => ['sylius_hipay_plugin.message_handler.new_order'];
        yield 'transaction information request message handler' => ['sylius_hipay_plugin.message_handler.transaction_information'];
        yield 'webhook consumer' => ['sylius_hipay_plugin.webhook.consumer'];
        yield 'pending notification repository' => ['sylius_hipay_plugin.repository.pending_notification'];
        yield 'hipay notifications schedule' => ['sylius_hipay_plugin.webhook.scheduler.schedule'];
        yield 'process pending batch handler' => ['sylius_hipay_plugin.webhook.scheduler.process_pending_batch_handler'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function interfaceAliasProvider(): iterable
    {
        yield ClientProviderInterface::class => [
            ClientProviderInterface::class,
            ClientProvider::class,
        ];
        yield AccountProviderInterface::class => [
            AccountProviderInterface::class,
            AccountProvider::class,
        ];
        yield OneyCategoryProviderInterface::class => [
            OneyCategoryProviderInterface::class,
            OneyCategoryProvider::class,
        ];
        yield OneyShippingMethodProviderInterface::class => [
            OneyShippingMethodProviderInterface::class,
            OneyShippingMethodProvider::class,
        ];
        yield PaymentProductProviderInterface::class => [
            PaymentProductProviderInterface::class,
            PaymentProductProvider::class,
        ];
        yield PaymentProductHandlerRegistryInterface::class => [
            PaymentProductHandlerRegistryInterface::class,
            PaymentProductHandlerRegistry::class,
        ];
        yield PaymentOrderRequestBuilderRegistryInterface::class => [
            PaymentOrderRequestBuilderRegistryInterface::class,
            PaymentOrderRequestBuilderRegistry::class,
        ];
        yield PaymentOrderRequestContextFactoryInterface::class => [
            PaymentOrderRequestContextFactoryInterface::class,
            PaymentOrderRequestContextFactory::class,
        ];
    }
}
