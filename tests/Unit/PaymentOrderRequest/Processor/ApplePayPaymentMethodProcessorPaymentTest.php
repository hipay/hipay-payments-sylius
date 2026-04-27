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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\PaymentOrderRequest\Processor;

use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\CardTokenPaymentMethod;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\ApplePayPaymentMethodProcessorPayment;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final class ApplePayPaymentMethodProcessorPaymentTest extends TestCase
{
    private ApplePayPaymentMethodProcessorPayment $processor;

    protected function setUp(): void
    {
        $this->processor = new ApplePayPaymentMethodProcessorPayment();
    }

    public function testProcessSetsPaymentProductFromPayload(): void
    {
        $orderRequest = new OrderRequest();

        $context = new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'apple-pay',
            payload: ['payment_product' => 'visa', 'token' => 'tok_apple_123'],
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );

        $this->processor->process($orderRequest, $context);

        $this->assertSame('visa', $orderRequest->payment_product);
    }

    public function testProcessSetsCardTokenPaymentMethod(): void
    {
        $orderRequest = new OrderRequest();

        $context = new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'apple-pay',
            payload: ['payment_product' => 'visa', 'token' => 'tok_apple_123'],
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );

        $this->processor->process($orderRequest, $context);

        $this->assertInstanceOf(CardTokenPaymentMethod::class, $orderRequest->paymentMethod);
        $this->assertSame('tok_apple_123', $orderRequest->paymentMethod->cardtoken);
    }

    public function testProcessDefaultsPaymentProductToCb(): void
    {
        $orderRequest = new OrderRequest();

        $context = new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'apple-pay',
            payload: [],
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );

        $this->processor->process($orderRequest, $context);

        $this->assertSame('cb', $orderRequest->payment_product);
    }

    public function testProcessDefaultsTokenToEmptyString(): void
    {
        $orderRequest = new OrderRequest();

        $context = new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'apple-pay',
            payload: [],
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );

        $this->processor->process($orderRequest, $context);

        $this->assertInstanceOf(CardTokenPaymentMethod::class, $orderRequest->paymentMethod);
        $this->assertSame('', $orderRequest->paymentMethod->cardtoken);
    }
}
