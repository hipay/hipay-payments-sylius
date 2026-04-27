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

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContextFactory;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class PaymentOrderRequestContextFactoryTest extends TestCase
{
    private RepositoryInterface&MockObject $accountRepository;

    private PaymentOrderRequestContextFactory $factory;

    protected function setUp(): void
    {
        $this->accountRepository = $this->createMock(RepositoryInterface::class);
        $this->factory = new PaymentOrderRequestContextFactory($this->accountRepository);
    }

    public function testBuildFromPaymentRequestReturnsCorrectContext(): void
    {
        $account = $this->createMock(AccountInterface::class);
        [$paymentRequest, $order, $payment] = $this->createValidPaymentRequest(
            accountCode: 'hipay_main',
            paymentProduct: 'visa',
            payload: ['token' => 'tok_abc'],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );

        $this->accountRepository
            ->method('findOneBy')
            ->with(['code' => 'hipay_main'])
            ->willReturn($account);

        $context = $this->factory->buildFromPaymentRequest($paymentRequest);

        $this->assertInstanceOf(PaymentOrderRequestContext::class, $context);
        $this->assertSame($order, $context->order);
        $this->assertSame($payment, $context->payment);
        $this->assertSame($paymentRequest, $context->paymentRequest);
        $this->assertSame($account, $context->account);
        $this->assertSame('visa', $context->paymentProduct);
        $this->assertSame(['token' => 'tok_abc'], $context->payload);
        $this->assertSame(PaymentRequestInterface::ACTION_CAPTURE, $context->action);
    }

    public function testBuildFromPaymentRequestThrowsWhenPaymentIsNotCorePayment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment is not a core payment');

        // Return a base Sylius payment (not the core one) to trigger the instanceof check
        $basePayment = $this->createMock(BasePaymentInterface::class);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getPayment')->willReturn($basePayment);

        $this->factory->buildFromPaymentRequest($paymentRequest);
    }

    public function testBuildFromPaymentRequestThrowsWhenOrderIsNotSet(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Order is not set');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn(null);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getPayment')->willReturn($payment);

        $this->factory->buildFromPaymentRequest($paymentRequest);
    }

    public function testBuildFromPaymentRequestThrowsWhenPaymentMethodIsNotSet(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment method is not set');

        $order = $this->createMock(OrderInterface::class);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getMethod')->willReturn(null);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getPayment')->willReturn($payment);

        $this->factory->buildFromPaymentRequest($paymentRequest);
    }

    public function testBuildFromPaymentRequestThrowsWhenAccountCodeIsNotString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment account is not set');

        [$paymentRequest] = $this->createValidPaymentRequest(
            accountCode: null,
            paymentProduct: 'card',
        );

        $this->factory->buildFromPaymentRequest($paymentRequest);
    }

    public function testBuildFromPaymentRequestThrowsWhenAccountIsNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Account with code "missing_account" not found');

        [$paymentRequest] = $this->createValidPaymentRequest(
            accountCode: 'missing_account',
            paymentProduct: 'card',
        );

        $this->accountRepository->method('findOneBy')->willReturn(null);

        $this->factory->buildFromPaymentRequest($paymentRequest);
    }

    public function testBuildFromPaymentRequestThrowsWhenPaymentProductIsNotString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment product is not set');

        [$paymentRequest] = $this->createValidPaymentRequest(
            accountCode: 'hipay_main',
            paymentProduct: null,
        );

        $this->accountRepository->method('findOneBy')->willReturn($this->createMock(AccountInterface::class));

        $this->factory->buildFromPaymentRequest($paymentRequest);
    }

    public function testGatewayConfigIsPassedToContext(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $gatewayConfigData = [
            'account' => 'hipay_main',
            'payment_product' => 'cb',
            'three_ds_mode' => '3ds_if_available',
        ];

        [$paymentRequest] = $this->createValidPaymentRequest(
            accountCode: 'hipay_main',
            paymentProduct: 'cb',
            payload: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
            gatewayConfig: $gatewayConfigData,
        );

        $this->accountRepository->method('findOneBy')->willReturn($account);

        $context = $this->factory->buildFromPaymentRequest($paymentRequest);

        $this->assertSame($gatewayConfigData, $context->gatewayConfig);
    }

    /**
     * @return array{PaymentRequestInterface, OrderInterface, PaymentInterface}
     */
    private function createValidPaymentRequest(
        ?string $accountCode,
        ?string $paymentProduct,
        array $payload = [],
        string $action = PaymentRequestInterface::ACTION_CAPTURE,
        ?array $gatewayConfig = null,
    ): array {
        $gatewayConfigData = $gatewayConfig ?? array_filter([
            'account' => $accountCode,
            'payment_product' => $paymentProduct,
        ], fn ($v) => null !== $v);

        $gatewayConfigMock = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfigMock->method('getConfig')->willReturn($gatewayConfigData);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfigMock);

        $order = $this->createMock(OrderInterface::class);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getDetails')->willReturn($payload);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getPayment')->willReturn($payment);
        $paymentRequest->method('getAction')->willReturn($action);

        return [$paymentRequest, $order, $payment];
    }
}
