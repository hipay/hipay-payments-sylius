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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\EventListener;

use HiPay\SyliusHiPayPlugin\Event\RequestCaptureEvent;
use HiPay\SyliusHiPayPlugin\EventListener\RequestCaptureListener;
use HiPay\SyliusHiPayPlugin\Payment\Capture\CaptureRequestResult;
use HiPay\SyliusHiPayPlugin\Payment\Capture\PaymentCaptureHandlerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class RequestCaptureListenerTest extends TestCase
{
    private RepositoryInterface&MockObject $paymentRepository;

    private PaymentCaptureHandlerInterface&MockObject $captureHandler;

    private RequestCaptureListener $listener;

    protected function setUp(): void
    {
        $this->paymentRepository = $this->createMock(RepositoryInterface::class);
        $this->captureHandler = $this->createMock(PaymentCaptureHandlerInterface::class);
        $this->listener = new RequestCaptureListener($this->paymentRepository, $this->captureHandler);
    }

    public function testItSetsFailureWhenPaymentIsNotFound(): void
    {
        $this->paymentRepository->method('find')->with(7)->willReturn(null);
        $this->captureHandler->expects($this->never())->method('capture');

        $event = new RequestCaptureEvent(7);
        ($this->listener)($event);

        $this->assertFalse($event->getResult()?->isSuccessful());
        $this->assertStringContainsString('not found', (string) $event->getResult()?->getError());
    }

    public function testItCapturesThePaymentMatchingTheEventPaymentId(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $this->paymentRepository->method('find')->with(7)->willReturn($payment);

        $this->captureHandler->expects($this->once())->method('capture')->with($payment)
            ->willReturn(CaptureRequestResult::success());

        $event = new RequestCaptureEvent(7);
        ($this->listener)($event);

        $this->assertTrue($event->getResult()?->isSuccessful());
        $this->assertNull($event->getResult()?->getError());
    }

    public function testItRelaysTheFailureResultFromTheHandler(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $this->paymentRepository->method('find')->with(7)->willReturn($payment);

        $this->captureHandler->method('capture')->willReturn(CaptureRequestResult::failure('not eligible'));

        $event = new RequestCaptureEvent(7);
        ($this->listener)($event);

        $this->assertFalse($event->getResult()?->isSuccessful());
        $this->assertSame('not eligible', $event->getResult()?->getError());
    }
}
