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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Event;

use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\SyliusHiPayPlugin\Event\BeforeOrderRequestEvent;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final class BeforeOrderRequestEventTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $orderRequest = new OrderRequest();
        $payment = $this->createMock(PaymentInterface::class);
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);

        $event = new BeforeOrderRequestEvent($orderRequest, $payment, $paymentRequest, 'capture');

        $this->assertSame($orderRequest, $event->getOrderRequest());
        $this->assertSame($payment, $event->getPayment());
        $this->assertSame($paymentRequest, $event->getPaymentRequest());
        $this->assertSame('capture', $event->getAction());
    }

    public function testOrderRequestIsMutable(): void
    {
        $originalRequest = new OrderRequest();
        $replacementRequest = new OrderRequest();
        $replacementRequest->orderid = 'REPLACED';

        $event = new BeforeOrderRequestEvent(
            $originalRequest,
            $this->createMock(PaymentInterface::class),
            $this->createMock(PaymentRequestInterface::class),
            'capture',
        );

        $event->setOrderRequest($replacementRequest);

        $this->assertSame($replacementRequest, $event->getOrderRequest());
        $this->assertSame('REPLACED', $event->getOrderRequest()->orderid);
    }

    public function testApiCallIsNotSkippedByDefault(): void
    {
        $event = new BeforeOrderRequestEvent(
            new OrderRequest(),
            $this->createMock(PaymentInterface::class),
            $this->createMock(PaymentRequestInterface::class),
            'capture',
        );

        $this->assertFalse($event->isApiCallSkipped());
        $this->assertNull($event->getAlternativeResponseData());
    }

    public function testSetAlternativeResponseDataSkipsApiCall(): void
    {
        $event = new BeforeOrderRequestEvent(
            new OrderRequest(),
            $this->createMock(PaymentInterface::class),
            $this->createMock(PaymentRequestInterface::class),
            'capture',
        );

        $alternativeData = ['state' => 'completed', 'transaction_reference' => 'ALT-123'];
        $event->setAlternativeResponseData($alternativeData);

        $this->assertTrue($event->isApiCallSkipped());
        $this->assertSame($alternativeData, $event->getAlternativeResponseData());
    }
}
