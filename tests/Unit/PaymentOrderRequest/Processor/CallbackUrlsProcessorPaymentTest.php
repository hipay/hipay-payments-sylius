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
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\CallbackUrlsProcessorPayment;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final class CallbackUrlsProcessorPaymentTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $urlGenerator;

    private CallbackUrlsProcessorPayment $processor;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->processor = new CallbackUrlsProcessorPayment($this->urlGenerator);
    }

    public function testProcessSetsAllCallbackUrlsToAfterPayUrl(): void
    {
        $hash = Uuid::v4();
        $afterPayUrl = 'https://shop.example.com/after-pay';
        $notifyUrl = 'https://shop.example.com/hipay/webhook';

        $this->urlGenerator
            ->method('generate')
            ->willReturnCallback(
                fn (string $route) => match ($route) {
                    'sylius_shop_order_after_pay' => $afterPayUrl,
                    'sylius_hipay_plugin_webhook' => $notifyUrl,
                    default => throw new InvalidArgumentException("Unexpected route: $route"),
                },
            );

        $context = $this->createContext((string) $hash);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame($afterPayUrl, $orderRequest->accept_url);
        $this->assertSame($afterPayUrl, $orderRequest->decline_url);
        $this->assertSame($afterPayUrl, $orderRequest->pending_url);
        $this->assertSame($afterPayUrl, $orderRequest->cancel_url);
        $this->assertSame($afterPayUrl, $orderRequest->exception_url);
        $this->assertSame($notifyUrl, $orderRequest->notify_url);
    }

    public function testProcessPassesHashToAfterPayUrlGeneration(): void
    {
        $hash = Uuid::v4();
        $capturedCalls = [];

        $this->urlGenerator
            ->method('generate')
            ->willReturnCallback(
                static function (string $route, array $params = []) use (&$capturedCalls): string {
                    $capturedCalls[] = ['route' => $route, 'params' => $params];

                    return 'https://shop.example.com/' . $route;
                },
            );

        $context = $this->createContext((string) $hash);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $afterPayCall = array_values(array_filter(
            $capturedCalls,
            static fn (array $call) => $call['route'] === 'sylius_shop_order_after_pay',
        ))[0] ?? null;

        $this->assertNotNull($afterPayCall, 'generate() was never called with sylius_shop_order_after_pay');
        $this->assertSame((string) $hash, $afterPayCall['params']['hash']);
    }

    public function testProcessHandlesNullHash(): void
    {
        $this->urlGenerator
            ->method('generate')
            ->willReturnCallback(
                fn (string $route, array $params = []) => match ($route) {
                    'sylius_shop_order_after_pay' => 'https://shop.example.com/after-pay?hash=' . ($params['hash'] ?? ''),
                    'sylius_hipay_plugin_webhook' => 'https://shop.example.com/webhook',
                    default => '',
                },
            );

        $context = $this->createContext(null);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertNotNull($orderRequest->accept_url);
        $this->assertNotNull($orderRequest->notify_url);
    }

    public function testProcessGeneratesNotifyUrlWithAbsoluteUrl(): void
    {
        $hash = Uuid::v4();
        $capturedCalls = [];

        $this->urlGenerator
            ->method('generate')
            ->willReturnCallback(
                static function (string $route, array $params = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH) use (&$capturedCalls): string {
                    $capturedCalls[] = [
                        'route' => $route,
                        'params' => $params,
                        'referenceType' => $referenceType,
                    ];

                    return 'https://shop.example.com/';
                },
            );

        $context = $this->createContext((string) $hash);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $notifyCall = array_values(array_filter(
            $capturedCalls,
            static fn (array $call) => $call['route'] === 'sylius_hipay_plugin_webhook',
        ))[0] ?? null;

        $this->assertNotNull($notifyCall, 'generate() was never called with sylius_hipay_plugin_webhook');
        $this->assertSame(UrlGeneratorInterface::ABSOLUTE_URL, $notifyCall['referenceType']);
        $this->assertSame([], $notifyCall['params']);
    }

    private function createContext(?string $uuidString): PaymentOrderRequestContext
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getHash')->willReturn(
            null !== $uuidString ? Uuid::fromString($uuidString) : null,
        );

        return new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $paymentRequest,
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'card',
            payload: [],
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );
    }
}
