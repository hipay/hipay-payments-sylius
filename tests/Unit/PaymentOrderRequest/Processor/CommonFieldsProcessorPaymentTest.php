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

use DateTimeImmutable;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\CommonFieldsProcessorPayment;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\Clock\MockClock;

final class CommonFieldsProcessorPaymentTest extends TestCase
{
    private MockClock $clock;

    private CommonFieldsProcessorPayment $processor;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-03-12 14:30:45'));
        $this->processor = new CommonFieldsProcessorPayment($this->clock);
    }

    public function testProcessSetsOrderIdFromNumberChannelAndTime(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('SHOP');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn('ORD-001');
        $order->method('getChannel')->willReturn($channel);
        $order->method('getCurrencyCode')->willReturn('EUR');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn(9999);

        $context = $this->createContext($order, $payment, PaymentRequestInterface::ACTION_CAPTURE);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame('ORD-001-SHOP-143045', $orderRequest->orderid);
    }

    public function testProcessSetsDescriptionWithOrderNumber(): void
    {
        $context = $this->createContextWithDefaults(PaymentRequestInterface::ACTION_CAPTURE);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame('Order #ORD-001', $orderRequest->description);
    }

    public function testProcessConvertsAmountFromCentsToDecimal(): void
    {
        $context = $this->createContextWithDefaults(PaymentRequestInterface::ACTION_CAPTURE, amountInCents: 12345);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame(123.45, $orderRequest->amount);
    }

    public function testProcessSetsCurrencyFromOrder(): void
    {
        $context = $this->createContextWithDefaults(PaymentRequestInterface::ACTION_CAPTURE);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame('EUR', $orderRequest->currency);
    }

    public function testProcessSetsShippingAndTaxToZero(): void
    {
        $context = $this->createContextWithDefaults(PaymentRequestInterface::ACTION_CAPTURE);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame(0, $orderRequest->shipping);
        $this->assertSame(0, $orderRequest->tax);
    }

    public function testProcessSetsOperationToSaleForCapture(): void
    {
        $context = $this->createContextWithDefaults(PaymentRequestInterface::ACTION_CAPTURE);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame('Sale', $orderRequest->operation);
    }

    public function testProcessSetsOperationToAuthorizationForAuthorize(): void
    {
        $context = $this->createContextWithDefaults(PaymentRequestInterface::ACTION_AUTHORIZE);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame('Authorization', $orderRequest->operation);
    }

    public function testProcessFallsBackToTokenValueWhenNumberIsNull(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('WEB');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn(null);
        $order->method('getTokenValue')->willReturn('tok_abc123');
        $order->method('getChannel')->willReturn($channel);
        $order->method('getCurrencyCode')->willReturn('USD');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn(5000);

        $context = $this->createContext($order, $payment, PaymentRequestInterface::ACTION_CAPTURE);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertStringStartsWith('tok_abc123-WEB-', $orderRequest->orderid);
    }

    public function testProcessSetsCidFromCustomerId(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(42);

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('SHOP');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn('ORD-001');
        $order->method('getChannel')->willReturn($channel);
        $order->method('getCurrencyCode')->willReturn('EUR');
        $order->method('getCustomer')->willReturn($customer);

        $payment = $this->createMock(PaymentInterface::class);

        $context = $this->createContext($order, $payment, PaymentRequestInterface::ACTION_CAPTURE);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame('42', $orderRequest->cid);
    }

    public function testProcessSetsLanguageFromLocale(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('SHOP');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn('ORD-001');
        $order->method('getChannel')->willReturn($channel);
        $order->method('getCurrencyCode')->willReturn('EUR');
        $order->method('getLocaleCode')->willReturn('fr_FR');

        $payment = $this->createMock(PaymentInterface::class);

        $context = $this->createContext($order, $payment, PaymentRequestInterface::ACTION_CAPTURE);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame('fr_FR', $orderRequest->language);
    }

    public function testProcessSetsSourceWithPluginInfo(): void
    {
        $context = $this->createContextWithDefaults(PaymentRequestInterface::ACTION_CAPTURE);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertIsArray($orderRequest->source);
        $this->assertSame('CMS', $orderRequest->source['source']);
        $this->assertSame('Sylius', $orderRequest->source['brand']);
        $this->assertArrayHasKey('brand_version', $orderRequest->source);
        $this->assertArrayHasKey('integration_version', $orderRequest->source);
    }

    public function testProcessFallsBackToChannelCodeDefaultWhenChannelIsNull(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn('ORD-999');
        $order->method('getChannel')->willReturn(null);
        $order->method('getCurrencyCode')->willReturn('GBP');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn(1000);

        $context = $this->createContext($order, $payment, PaymentRequestInterface::ACTION_CAPTURE);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertStringContainsString('channel_code', $orderRequest->orderid);
    }

    private function createContextWithDefaults(
        string $action,
        int $amountInCents = 9999,
        string $currencyCode = 'EUR',
    ): PaymentOrderRequestContext {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getCode')->willReturn('SHOP');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn('ORD-001');
        $order->method('getChannel')->willReturn($channel);
        $order->method('getCurrencyCode')->willReturn($currencyCode);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn($amountInCents);

        return $this->createContext($order, $payment, $action);
    }

    private function createContext(
        OrderInterface $order,
        PaymentInterface $payment,
        string $action,
    ): PaymentOrderRequestContext {
        return new PaymentOrderRequestContext(
            order: $order,
            payment: $payment,
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'card',
            payload: [],
            gatewayConfig: [],
            action: $action,
        );
    }
}
