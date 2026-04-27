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
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\ApplePayCustomDataProcessorPayment;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

final class ApplePayCustomDataProcessorPaymentTest extends TestCase
{
    private ApplePayCustomDataProcessorPayment $processor;

    protected function setUp(): void
    {
        $serializer = new Serializer([], [new JsonEncoder()]);
        $this->processor = new ApplePayCustomDataProcessorPayment($serializer);
    }

    public function testProcessSetsIsApplePayInCustomData(): void
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

        $this->assertIsString($orderRequest->custom_data);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($orderRequest->custom_data, true);
        $this->assertSame(1, $decoded['isApplePay']);
    }

    public function testProcessMergesWithExistingCustomData(): void
    {
        $orderRequest = new OrderRequest();
        $orderRequest->custom_data = json_encode(['shipping_description' => 'Standard', 'captureType' => 'automatic']);

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

        $this->assertIsString($orderRequest->custom_data);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($orderRequest->custom_data, true);
        $this->assertSame(1, $decoded['isApplePay']);
        $this->assertSame('Standard', $decoded['shipping_description']);
        $this->assertSame('automatic', $decoded['captureType']);
    }

    public function testProcessHandlesEmptyCustomData(): void
    {
        $orderRequest = new OrderRequest();
        $orderRequest->custom_data = '';

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

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($orderRequest->custom_data, true);
        $this->assertSame(['isApplePay' => 1], $decoded);
    }
}
