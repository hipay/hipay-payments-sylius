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
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\BrowserInfoProcessorPayment;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class BrowserInfoProcessorPaymentTest extends TestCase
{
    private RequestStack $requestStack;

    private BrowserInfoProcessorPayment $processor;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->processor = new BrowserInfoProcessorPayment($this->requestStack);
    }

    public function testProcessSetsClientIpFromCurrentRequest(): void
    {
        $this->requestStack->push(
            Request::create('/', server: ['REMOTE_ADDR' => '192.168.1.42']),
        );

        $context = $this->createContext([]);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame('192.168.1.42', $orderRequest->ipaddr);
    }

    public function testProcessFallsBackToDefaultIpWhenNoRequest(): void
    {
        $context = $this->createContext([]);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame('0.0.0.0', $orderRequest->ipaddr);
    }

    public function testProcessSetsDeviceFingerprintFromPayload(): void
    {
        $this->requestStack->push(Request::create('/'));
        $context = $this->createContext(['device_fingerprint' => 'abc-fingerprint-xyz']);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame('abc-fingerprint-xyz', $orderRequest->device_fingerprint);
    }

    public function testProcessSetsDeviceFingerprintToEmptyStringWhenMissing(): void
    {
        $this->requestStack->push(Request::create('/'));
        $context = $this->createContext([]);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame('', $orderRequest->device_fingerprint);
    }

    public function testProcessIgnoresNonStringDeviceFingerprint(): void
    {
        $this->requestStack->push(Request::create('/'));
        $context = $this->createContext(['device_fingerprint' => 12345]);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertSame('', $orderRequest->device_fingerprint);
    }

    public function testProcessSetsBrowserInfoWhenPayloadContainsBrowserInfo(): void
    {
        $this->requestStack->push(Request::create('/'));

        $context = $this->createContext([
            'browser_info' => [
                'java_enabled' => false,
                'javascript_enabled' => true,
                'language' => 'fr-FR',
                'color_depth' => 32,
                'screen_height' => 1080,
                'screen_width' => 1920,
                'timezone' => '60',
                'http_user_agent' => 'Mozilla/5.0',
            ],
        ]);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertNotNull($orderRequest->browser_info);
        $this->assertFalse($orderRequest->browser_info->java_enabled);
        $this->assertTrue($orderRequest->browser_info->javascript_enabled);
        $this->assertSame('fr-FR', $orderRequest->browser_info->language);
        $this->assertSame(32, $orderRequest->browser_info->color_depth);
        $this->assertSame(1080, $orderRequest->browser_info->screen_height);
        $this->assertSame(1920, $orderRequest->browser_info->screen_width);
        $this->assertSame('60', $orderRequest->browser_info->timezone);
        $this->assertSame('Mozilla/5.0', $orderRequest->browser_info->http_user_agent);
    }

    public function testProcessDoesNotSetBrowserInfoWhenMissingFromPayload(): void
    {
        $this->requestStack->push(Request::create('/'));
        $context = $this->createContext(['token' => 'tok_123']);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertNull($orderRequest->browser_info);
    }

    public function testProcessDoesNotSetBrowserInfoWhenBrowserInfoIsNotArray(): void
    {
        $this->requestStack->push(Request::create('/'));
        $context = $this->createContext(['browser_info' => 'invalid_string']);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertNull($orderRequest->browser_info);
    }

    public function testProcessUsesBrowserInfoDefaultsWhenKeysAreMissing(): void
    {
        $this->requestStack->push(Request::create('/'));
        $context = $this->createContext(['browser_info' => []]);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertNotNull($orderRequest->browser_info);
        $this->assertFalse($orderRequest->browser_info->java_enabled);
        $this->assertTrue($orderRequest->browser_info->javascript_enabled);
        $this->assertSame('fr', $orderRequest->browser_info->language);
        $this->assertSame(24, $orderRequest->browser_info->color_depth);
        $this->assertSame(900, $orderRequest->browser_info->screen_height);
        $this->assertSame(1600, $orderRequest->browser_info->screen_width);
        $this->assertSame('0', $orderRequest->browser_info->timezone);
    }

    private function createContext(array $payload): PaymentOrderRequestContext
    {
        return new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'card',
            payload: $payload,
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );
    }
}
