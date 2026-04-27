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
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\CardPaymentMethodProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentProduct\ThreeDS\ThreeDSMode;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final class CardPaymentMethodProcessorPaymentTest extends TestCase
{
    private CardPaymentMethodProcessorPayment $processor;

    protected function setUp(): void
    {
        $this->processor = new CardPaymentMethodProcessorPayment();
    }

    public function testProcessSetsPaymentProductFromPayload(): void
    {
        $context = $this->createContext(
            payload: ['payment_product' => 'visa', 'token' => 'tok_123'],
            gatewayConfig: [],
        );
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame('visa', $orderRequest->payment_product);
    }

    public function testProcessDefaultsToCardWhenPaymentProductMissingFromPayload(): void
    {
        $context = $this->createContext(
            payload: ['token' => 'tok_123'],
            gatewayConfig: [],
        );
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame('cb', $orderRequest->payment_product);
    }

    public function testProcessDefaultsToCardWhenPaymentProductIsNotString(): void
    {
        $context = $this->createContext(
            payload: ['payment_product' => 999, 'token' => 'tok_123'],
            gatewayConfig: [],
        );
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame('cb', $orderRequest->payment_product);
    }

    public function testProcessSetsCardTokenFromPayload(): void
    {
        $context = $this->createContext(
            payload: ['payment_product' => 'visa', 'token' => 'tok_card_abc'],
            gatewayConfig: [],
        );
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertInstanceOf(CardTokenPaymentMethod::class, $orderRequest->paymentMethod);
        $this->assertSame('tok_card_abc', $orderRequest->paymentMethod->cardtoken);
    }

    public function testProcessSetsCardTokenToEmptyStringWhenMissingFromPayload(): void
    {
        $context = $this->createContext(
            payload: ['payment_product' => 'cb'],
            gatewayConfig: [],
        );
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertInstanceOf(CardTokenPaymentMethod::class, $orderRequest->paymentMethod);
        $this->assertSame('', $orderRequest->paymentMethod->cardtoken);
    }

    public function testProcessSetsThreeDSModeFromGatewayConfig(): void
    {
        $context = $this->createContext(
            payload: ['token' => 'tok_123'],
            gatewayConfig: ['three_ds_mode' => ThreeDSMode::MANDATORY],
        );
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertInstanceOf(CardTokenPaymentMethod::class, $orderRequest->paymentMethod);
        $this->assertSame(2, $orderRequest->paymentMethod->authentication_indicator);
    }

    public function testProcessSetsThreeDSModeFromNestedConfiguration(): void
    {
        $context = $this->createContext(
            payload: ['token' => 'tok_123'],
            gatewayConfig: [
                'configuration' => ['three_ds_mode' => ThreeDSMode::DISABLED],
            ],
        );
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertInstanceOf(CardTokenPaymentMethod::class, $orderRequest->paymentMethod);
        $this->assertSame(0, $orderRequest->paymentMethod->authentication_indicator);
    }

    public function testProcessDefaultsToIfAvailableThreeDSModeWhenNotConfigured(): void
    {
        $context = $this->createContext(
            payload: ['token' => 'tok_123'],
            gatewayConfig: [],
        );
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertInstanceOf(CardTokenPaymentMethod::class, $orderRequest->paymentMethod);
        $this->assertSame(1, $orderRequest->paymentMethod->authentication_indicator);
    }

    /**
     * @dataProvider threeDsModeProvider
     */
    public function testProcessMapsThreeDSModeCorrectly(string $mode, int $expectedIndicator): void
    {
        $context = $this->createContext(
            payload: ['token' => 'tok_123'],
            gatewayConfig: ['three_ds_mode' => $mode],
        );
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame($expectedIndicator, $orderRequest->paymentMethod->authentication_indicator);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function threeDsModeProvider(): iterable
    {
        yield 'disabled' => [ThreeDSMode::DISABLED, 0];
        yield 'if_available' => [ThreeDSMode::IF_AVAILABLE, 1];
        yield 'mandatory' => [ThreeDSMode::MANDATORY, 2];
        yield 'unknown defaults to 1' => ['unknown_mode', 1];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $gatewayConfig
     */
    private function createContext(array $payload, array $gatewayConfig): PaymentOrderRequestContext
    {
        return new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'card',
            payload: $payload,
            gatewayConfig: $gatewayConfig,
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );
    }
}
