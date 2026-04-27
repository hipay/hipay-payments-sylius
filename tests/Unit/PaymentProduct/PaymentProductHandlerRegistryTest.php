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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\PaymentProduct;

use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;

final class PaymentProductHandlerRegistryTest extends TestCase
{
    public function testGetReturnsHandlerByCode(): void
    {
        $cardHandler = $this->createMock(PaymentProductHandlerInterface::class);
        $cardHandler->method('getCode')->willReturn('card');

        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->with('card')->willReturn(true);
        $locator->method('get')->with('card')->willReturn($cardHandler);

        $registry = new PaymentProductHandlerRegistry($locator, ['card' => $cardHandler]);

        $this->assertSame($cardHandler, $registry->get('card'));
    }

    public function testGetThrowsOnUnknownCode(): void
    {
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->with('unknown')->willReturn(false);

        $registry = new PaymentProductHandlerRegistry($locator, ['card' => $this->createMock(PaymentProductHandlerInterface::class)]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment product handler "unknown" not found');

        $registry->get('unknown');
    }

    public function testGetForPaymentProductReturnsMatchingHandler(): void
    {
        $cardHandler = $this->createMock(PaymentProductHandlerInterface::class);
        $cardHandler->method('getCode')->willReturn('card');
        $cardHandler->method('supports')->with('visa')->willReturn(true);

        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('get')->with('card')->willReturn($cardHandler);

        $registry = new PaymentProductHandlerRegistry($locator, ['card' => $cardHandler]);

        $this->assertSame($cardHandler, $registry->getForPaymentProduct('visa'));
    }

    public function testGetForPaymentProductReturnsNullWhenNoMatch(): void
    {
        $cardHandler = $this->createMock(PaymentProductHandlerInterface::class);
        $cardHandler->method('getCode')->willReturn('card');
        $cardHandler->method('supports')->with('paypal')->willReturn(false);

        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('get')->with('card')->willReturn($cardHandler);

        $registry = new PaymentProductHandlerRegistry($locator, ['card' => $cardHandler]);

        $this->assertNull($registry->getForPaymentProduct('paypal'));
    }

    public function testGetForPaymentMethodReturnsHandlerFromGatewayConfig(): void
    {
        $cardHandler = $this->createMock(PaymentProductHandlerInterface::class);
        $cardHandler->method('getCode')->willReturn('card');
        $cardHandler->method('supports')->with('card')->willReturn(true);

        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn(['payment_product' => 'card']);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('get')->with('card')->willReturn($cardHandler);

        $registry = new PaymentProductHandlerRegistry($locator, ['card' => $cardHandler]);

        $this->assertSame($cardHandler, $registry->getForPaymentMethod($paymentMethod));
    }

    public function testGetForPaymentMethodReturnsNullWhenNoPaymentProduct(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn([]);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $cardHandler = $this->createMock(PaymentProductHandlerInterface::class);
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('get')->with('card')->willReturn($cardHandler);

        $registry = new PaymentProductHandlerRegistry($locator, ['card' => $cardHandler]);

        $this->assertNull($registry->getForPaymentMethod($paymentMethod));
    }

    public function testGetForPaymentMethodReturnsNullWhenNoGatewayConfig(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn(null);

        $cardHandler = $this->createMock(PaymentProductHandlerInterface::class);
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('get')->with('card')->willReturn($cardHandler);

        $registry = new PaymentProductHandlerRegistry($locator, ['card' => $cardHandler]);

        $this->assertNull($registry->getForPaymentMethod($paymentMethod));
    }

    public function testGetAllLazyLoadsHandlers(): void
    {
        $cardHandler = $this->createMock(PaymentProductHandlerInterface::class);
        $cardHandler->method('getCode')->willReturn('card');

        $locator = $this->createMock(ContainerInterface::class);
        $locator->expects($this->once())->method('get')->with('card')->willReturn($cardHandler);

        $registry = new PaymentProductHandlerRegistry($locator, ['card' => $cardHandler]);

        $all1 = $registry->getAll();
        $all2 = $registry->getAll();

        $this->assertSame($all1, $all2);
        $this->assertCount(1, $all1);
        $this->assertArrayHasKey('card', $all1);
        $this->assertSame($cardHandler, $all1['card']);
    }
}
