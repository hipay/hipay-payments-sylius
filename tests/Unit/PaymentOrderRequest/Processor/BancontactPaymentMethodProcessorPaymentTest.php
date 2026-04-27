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
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\BancontactPaymentMethodProcessorPayment;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final class BancontactPaymentMethodProcessorPaymentTest extends TestCase
{
    private BancontactPaymentMethodProcessorPayment $processor;

    protected function setUp(): void
    {
        $this->processor = new BancontactPaymentMethodProcessorPayment();
    }

    public function testProcessSetsPaymentProductToBancontact(): void
    {
        $orderRequest = new OrderRequest();

        $context = new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'bancontact',
            payload: [],
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );

        $this->processor->process($orderRequest, $context);

        $this->assertSame('bancontact', $orderRequest->payment_product);
    }

    public function testProcessDoesNotSetPaymentMethod(): void
    {
        $orderRequest = new OrderRequest();

        $context = new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'bancontact',
            payload: [],
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );

        $this->processor->process($orderRequest, $context);

        $this->assertNull($orderRequest->paymentMethod);
    }
}
