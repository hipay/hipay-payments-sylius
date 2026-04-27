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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Twig\Component\Shop;

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Event\CheckoutPaymentDetailsDecodedEvent;
use HiPay\SyliusHiPayPlugin\Event\CheckoutPaymentDetailsPersistedEvent;
use HiPay\SyliusHiPayPlugin\Event\CheckoutSdkConfigResolvedEvent;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\PaymentEligibilityValidatorRegistryInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerRegistryInterface;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\CheckoutJsSdkConfigFactory;
use HiPay\SyliusHiPayPlugin\Twig\Component\Shop\HostedFieldsComponent;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\LiveResponder;

final class HostedFieldsComponentTest extends TestCase
{
    public function testGetJsSdkConfigReturnsEmptyWhenNoPaymentMethod(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $component = $this->createHostedFieldsComponent(
            $this->createMock(AccountProviderInterface::class),
            $this->createMock(PaymentProductHandlerRegistryInterface::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(DecoderInterface::class),
            $dispatcher,
        );
        $component->paymentMethod = null;

        $this->assertSame([], $component->getJsSdkConfig());
    }

    public function testGetJsSdkConfigReturnsEmptyWhenNoHandler(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $account = $this->createMock(AccountInterface::class);
        $accountProvider = $this->createMock(AccountProviderInterface::class);
        $accountProvider->method('getByPaymentMethod')->willReturn($account);

        $registry = $this->createMock(PaymentProductHandlerRegistryInterface::class);
        $registry->method('getForPaymentMethod')->willReturn(null);

        $component = $this->createHostedFieldsComponent(
            $accountProvider,
            $registry,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(DecoderInterface::class),
            $dispatcher,
        );
        $component->paymentMethod = new PaymentMethod();

        $this->assertSame([], $component->getJsSdkConfig());
    }

    public function testGetJsSdkConfigReturnsEmptyWhenNoAccount(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $handler = $this->createMock(PaymentProductHandlerInterface::class);
        $registry = $this->createMock(PaymentProductHandlerRegistryInterface::class);
        $registry->method('getForPaymentMethod')->willReturn($handler);

        $accountProvider = $this->createMock(AccountProviderInterface::class);
        $accountProvider->method('getByPaymentMethod')->willReturn(null);

        $component = $this->createHostedFieldsComponent(
            $accountProvider,
            $registry,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(DecoderInterface::class),
            $dispatcher,
        );
        $component->paymentMethod = new PaymentMethod();

        $this->assertSame([], $component->getJsSdkConfig());
    }

    public function testGetJsSdkConfigReturnsFullConfigWhenAllPresent(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('getPublicUsernameForCurrentEnv')->willReturn('public-user');
        $account->method('getPublicPasswordForCurrentEnv')->willReturn('public-pass');
        $account->method('isTestMode')->willReturn(true);
        $account->method('isDebugMode')->willReturn(false);

        $handler = $this->createMock(PaymentProductHandlerInterface::class);
        $handler->method('getCode')->willReturn('card');
        $handler->method('getJsInitConfig')->willReturn(['template' => 'auto']);

        $accountProvider = $this->createMock(AccountProviderInterface::class);
        $accountProvider->method('getByPaymentMethod')->willReturn($account);

        $registry = $this->createMock(PaymentProductHandlerRegistryInterface::class);
        $registry->method('getForPaymentMethod')->willReturn($handler);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())->method('dispatch')->with(
            $this->callback(static fn ($e) => $e instanceof CheckoutSdkConfigResolvedEvent),
        )->willReturnCallback(static fn ($event) => $event);

        $component = $this->createHostedFieldsComponent(
            $accountProvider,
            $registry,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(DecoderInterface::class),
            $dispatcher,
        );
        $component->paymentMethod = new PaymentMethod();

        $config = $component->getJsSdkConfig();

        $this->assertSame('card', $config['product']);
        $this->assertSame('public-user', $config['username']);
        $this->assertSame('public-pass', $config['password']);
        $this->assertSame('stage', $config['environment']);
        $this->assertFalse($config['debug']);
        $this->assertSame('fr', $config['lang']);
        $this->assertSame(['template' => 'auto'], $config['configuration']);
        $this->assertArrayHasKey('eligibility', $config);
        $this->assertFalse($config['eligibility']['blocked']);
        $this->assertArrayHasKey('clientMessages', $config);
        $this->assertArrayHasKey('sdkLoadFailed', $config['clientMessages']);
        $this->assertArrayHasKey('paymentProcessingFailed', $config['clientMessages']);
    }

    public function testGetJsSdkConfigUsesGatewayPaymentProductWhenConfigured(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('getPublicUsernameForCurrentEnv')->willReturn('public-user');
        $account->method('getPublicPasswordForCurrentEnv')->willReturn('public-pass');
        $account->method('isTestMode')->willReturn(true);
        $account->method('isDebugMode')->willReturn(false);

        $handler = $this->createMock(PaymentProductHandlerInterface::class);
        $handler->method('getCode')->willReturn('oney');
        $handler->method('getJsInitConfig')->willReturn(['template' => 'auto']);

        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn(['payment_product' => '3xcb']);

        $accountProvider = $this->createMock(AccountProviderInterface::class);
        $accountProvider->method('getByPaymentMethod')->willReturn($account);

        $registry = $this->createMock(PaymentProductHandlerRegistryInterface::class);
        $registry->method('getForPaymentMethod')->willReturn($handler);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static fn ($event) => $event);

        $component = $this->createHostedFieldsComponent(
            $accountProvider,
            $registry,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(DecoderInterface::class),
            $dispatcher,
        );
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);
        $component->paymentMethod = $paymentMethod;

        $config = $component->getJsSdkConfig();

        $this->assertSame('3xcb', $config['product']);
    }

    public function testProcessPaymentDoesNothingWhenNoPayment(): void
    {
        $serializer = $this->createMock(DecoderInterface::class);
        $serializer->expects($this->never())->method('decode');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $component = $this->createHostedFieldsComponent(
            $this->createMock(AccountProviderInterface::class),
            $this->createMock(PaymentProductHandlerRegistryInterface::class),
            $em,
            $serializer,
            $dispatcher,
        );
        $component->payment = null;

        $component->processPayment('{}');
    }

    public function testProcessPaymentDecodesAndPersistsDetails(): void
    {
        $payment = new Payment();
        $decoded = ['transaction_reference' => 'ref-123', 'token' => 'tok'];

        $serializer = $this->createMock(DecoderInterface::class);
        $serializer->expects($this->once())->method('decode')->with('{"ref":"123"}', 'json')->willReturn($decoded);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($payment);
        $em->expects($this->once())->method('flush');

        $dispatched = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))->method('dispatch')->willReturnCallback(function ($event) use (&$dispatched) {
            $dispatched[] = $event;

            return $event;
        });

        $component = $this->createHostedFieldsComponent(
            $this->createMock(AccountProviderInterface::class),
            $this->createMock(PaymentProductHandlerRegistryInterface::class),
            $em,
            $serializer,
            $dispatcher,
        );
        $component->payment = $payment;

        $liveResponder = new LiveResponder();
        $reflection = new ReflectionClass($component);
        $property = $reflection->getProperty('liveResponder');
        $property->setAccessible(true);
        $property->setValue($component, $liveResponder);

        $component->processPayment('{"ref":"123"}');

        $this->assertInstanceOf(CheckoutPaymentDetailsDecodedEvent::class, $dispatched[0]);
        $this->assertInstanceOf(CheckoutPaymentDetailsPersistedEvent::class, $dispatched[1]);
        $this->assertSame($decoded, $payment->getDetails());
    }

    public function testProcessPaymentUsesDetailsModifiedByDecodedEventListener(): void
    {
        $payment = new Payment();

        $serializer = $this->createMock(DecoderInterface::class);
        $serializer->method('decode')->willReturn(['token' => 'original']);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($payment);
        $em->expects($this->once())->method('flush');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))->method('dispatch')->willReturnCallback(function ($event) {
            if ($event instanceof CheckoutPaymentDetailsDecodedEvent) {
                $event->setDetails(['token' => 'mutated', 'extra' => true]);
            }

            return $event;
        });

        $component = $this->createHostedFieldsComponent(
            $this->createMock(AccountProviderInterface::class),
            $this->createMock(PaymentProductHandlerRegistryInterface::class),
            $em,
            $serializer,
            $dispatcher,
        );
        $component->payment = $payment;

        $liveResponder = new LiveResponder();
        $reflection = new ReflectionClass($component);
        $property = $reflection->getProperty('liveResponder');
        $property->setAccessible(true);
        $property->setValue($component, $liveResponder);

        $component->processPayment('{}');

        $this->assertSame(['token' => 'mutated', 'extra' => true], $payment->getDetails());
    }

    private function createHostedFieldsComponent(
        AccountProviderInterface $accountProvider,
        PaymentProductHandlerRegistryInterface $registry,
        EntityManagerInterface $entityManager,
        DecoderInterface $decoder,
        EventDispatcherInterface $eventDispatcher,
    ): HostedFieldsComponent {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        $paymentEligibilityValidatorRegistry = $this->createMock(PaymentEligibilityValidatorRegistryInterface::class);

        $checkoutJsSdkConfigFactory = new CheckoutJsSdkConfigFactory(
            $accountProvider,
            $registry,
            $eventDispatcher,
            $paymentEligibilityValidatorRegistry,
            $translator,
        );

        return new HostedFieldsComponent(
            $checkoutJsSdkConfigFactory,
            $entityManager,
            $decoder,
            $eventDispatcher,
        );
    }
}
