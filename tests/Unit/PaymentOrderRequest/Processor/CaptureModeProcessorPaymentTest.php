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

use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\CaptureModeProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Capture\CaptureMode;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final class CaptureModeProcessorPaymentTest extends TestCase
{
    private CaptureModeProcessorPayment $processor;

    protected function setUp(): void
    {
        $this->processor = new CaptureModeProcessorPayment();
    }

    public function testProcessSetsAuthorizationOperationWhenUseAuthorizeIsTrue(): void
    {
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $this->createContext(['use_authorize' => true]));

        $this->assertSame(CaptureMode::ON_DEMAND->value, $orderRequest->operation);
    }

    public function testProcessDefaultsToSaleWhenUseAuthorizeIsMissing(): void
    {
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $this->createContext([]));

        $this->assertSame(CaptureMode::INSTANT->value, $orderRequest->operation);
    }

    public function testProcessSetsOperationOnHostedPageRequest(): void
    {
        $request = new HostedPaymentPageRequest();

        $this->processor->process($request, $this->createContext(['use_authorize' => true]));

        $this->assertSame(CaptureMode::ON_DEMAND->value, $request->operation);
    }

    /**
     * @param array<string, mixed> $gatewayConfig
     */
    private function createContext(array $gatewayConfig): PaymentOrderRequestContext
    {
        return new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'card',
            payload: [],
            gatewayConfig: $gatewayConfig,
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );
    }
}
