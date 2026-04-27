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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\OrderPay\Handler;

use HiPay\SyliusHiPayPlugin\OrderPay\Handler\HiPayPaymentStateFlashHandlerDecorator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\CoreBundle\OrderPay\Handler\PaymentStateFlashHandlerInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Symfony\Component\HttpFoundation\Request;

final class HiPayPaymentStateFlashHandlerDecoratorTest extends TestCase
{
    private PaymentStateFlashHandlerInterface&MockObject $inner;

    private HiPayPaymentStateFlashHandlerDecorator $decorator;

    protected function setUp(): void
    {
        $this->inner = $this->createMock(PaymentStateFlashHandlerInterface::class);
        $this->decorator = new HiPayPaymentStateFlashHandlerDecorator($this->inner);
    }

    public function testSkipsNativeFlashWhenHiPayRequestAttributeIsSet(): void
    {
        $request = new Request();
        $request->attributes->set(HiPayPaymentStateFlashHandlerDecorator::REQUEST_ATTR_HIPAY_PAYMENT, true);

        $config = $this->createMock(RequestConfiguration::class);
        $config->method('getRequest')->willReturn($request);

        $this->inner->expects($this->never())->method('handle');

        $this->decorator->handle($config, 'completed');
    }

    public function testDelegatesToInnerWhenHiPayAttributeIsAbsent(): void
    {
        $request = new Request();

        $config = $this->createMock(RequestConfiguration::class);
        $config->method('getRequest')->willReturn($request);

        $this->inner->expects($this->once())->method('handle')->with($config, 'failed');

        $this->decorator->handle($config, 'failed');
    }

    public function testDelegatesWhenHiPayAttributeIsFalse(): void
    {
        $request = new Request();
        $request->attributes->set(HiPayPaymentStateFlashHandlerDecorator::REQUEST_ATTR_HIPAY_PAYMENT, false);

        $config = $this->createMock(RequestConfiguration::class);
        $config->method('getRequest')->willReturn($request);

        $this->inner->expects($this->once())->method('handle');

        $this->decorator->handle($config, 'completed');
    }
}
