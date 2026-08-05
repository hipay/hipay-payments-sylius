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
use HiPay\Fullservice\Gateway\Request\PaymentMethod\PhonePaymentMethod;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\BancomatPayPaymentMethodProcessorPayment;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final class BancomatPayPaymentMethodProcessorPaymentTest extends TestCase
{
    private BancomatPayPaymentMethodProcessorPayment $processor;

    protected function setUp(): void
    {
        $this->processor = new BancomatPayPaymentMethodProcessorPayment();
    }

    public function testProcessSetsPaymentProductToBancomatPay(): void
    {
        $orderRequest = new OrderRequest();

        $context = new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'bancomatpay',
            payload: [],
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );

        $this->processor->process($orderRequest, $context);

        $this->assertSame('bancomatpay', $orderRequest->payment_product);
    }

    public function testProcessSetsPhonePaymentMethod(): void
    {
        $orderRequest = new OrderRequest();

        $context = new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'bancomatpay',
            payload: ['phone' => '+393331234567'],
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );

        $this->processor->process($orderRequest, $context);

        $this->assertInstanceOf(PhonePaymentMethod::class, $orderRequest->paymentMethod);
    }

    public function testProcessSetsPhoneFromPayload(): void
    {
        $orderRequest = new OrderRequest();

        $context = new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'bancomatpay',
            payload: ['phone' => '+393331234567'],
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );

        $this->processor->process($orderRequest, $context);

        /** @var PhonePaymentMethod $method */
        $method = $orderRequest->paymentMethod;
        $this->assertSame('+393331234567', $method->phone);
    }

    public function testProcessFallsBackToEmptyPhoneWhenNotInPayload(): void
    {
        $orderRequest = new OrderRequest();

        $context = new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'bancomatpay',
            payload: [],
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );

        $this->processor->process($orderRequest, $context);

        /** @var PhonePaymentMethod $method */
        $method = $orderRequest->paymentMethod;
        $this->assertSame('', $method->phone);
    }
}
