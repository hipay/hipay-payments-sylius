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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\PaymentOrderRequest;

use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestBuilder;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestProcessorInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final class PaymentOrderRequestBuilderTest extends TestCase
{
    public function testBuildRunsAllProcessorsAndReturnsOrderRequest(): void
    {
        $context = $this->createContext();

        $processor1 = $this->createMock(PaymentOrderRequestProcessorInterface::class);
        $processor2 = $this->createMock(PaymentOrderRequestProcessorInterface::class);

        $processor1->expects(self::once())
            ->method('process')
            ->with(self::isInstanceOf(OrderRequest::class), $context);

        $processor2->expects(self::once())
            ->method('process')
            ->with(self::isInstanceOf(OrderRequest::class), $context);

        $builder = new PaymentOrderRequestBuilder(['card'], [$processor1, $processor2]);

        $result = $builder->build($context);

        $this->assertInstanceOf(OrderRequest::class, $result);
    }

    public function testBuildWithNoProcessorsReturnsEmptyOrderRequest(): void
    {
        $context = $this->createContext();
        $builder = new PaymentOrderRequestBuilder(['card'], []);

        $result = $builder->build($context);

        $this->assertInstanceOf(OrderRequest::class, $result);
    }

    public function testBuildCallsProcessorsInOrder(): void
    {
        $context = $this->createContext();
        $callOrder = [];

        $processor1 = $this->createMock(PaymentOrderRequestProcessorInterface::class);
        $processor1->method('process')->willReturnCallback(function () use (&$callOrder): void {
            $callOrder[] = 'first';
        });

        $processor2 = $this->createMock(PaymentOrderRequestProcessorInterface::class);
        $processor2->method('process')->willReturnCallback(function () use (&$callOrder): void {
            $callOrder[] = 'second';
        });

        $builder = new PaymentOrderRequestBuilder(['card'], [$processor1, $processor2]);
        $builder->build($context);

        $this->assertSame(['first', 'second'], $callOrder);
    }

    public function testSupportsReturnsTrueForMatchingPaymentProduct(): void
    {
        $builder = new PaymentOrderRequestBuilder(['visa'], []);

        $this->assertTrue($builder->supports('visa'));
    }

    public function testSupportsReturnsFalseForDifferentPaymentProduct(): void
    {
        $builder = new PaymentOrderRequestBuilder(['visa'], []);

        $this->assertFalse($builder->supports('mastercard'));
    }

    public function testSupportsIsCaseSensitive(): void
    {
        $builder = new PaymentOrderRequestBuilder(['card'], []);

        $this->assertFalse($builder->supports('CARD'));
        $this->assertFalse($builder->supports('Card'));
        $this->assertTrue($builder->supports('card'));
    }

    private function createContext(): PaymentOrderRequestContext
    {
        return new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'card',
            payload: [],
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );
    }
}
