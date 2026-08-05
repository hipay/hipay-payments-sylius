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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Payment\Capture\Request\Processor;

use HiPay\Fullservice\Gateway\Request\Maintenance\MaintenanceRequest;
use HiPay\SyliusHiPayPlugin\Payment\Capture\Request\CaptureRequestContext;
use HiPay\SyliusHiPayPlugin\Payment\Capture\Request\Processor\CaptureRequestOperationProcessor;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class CaptureRequestOperationProcessorTest extends TestCase
{
    public function testItSetsOperationOperationIdAndFullAmount(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn('000000018');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getId')->willReturn(17);
        $payment->method('getAmount')->willReturn(1661);

        $request = new MaintenanceRequest();
        (new CaptureRequestOperationProcessor())->process($request, new CaptureRequestContext($order, $payment, 'txn_ref_123'));

        $this->assertSame('capture', $request->operation);
        $this->assertSame('capture-000000018-17', $request->operation_id);
        $this->assertSame(16.61, $request->amount);
    }

    public function testItFallsBackToOrderIdWhenNumberIsNull(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn(null);
        $order->method('getId')->willReturn(18);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getId')->willReturn(17);
        $payment->method('getAmount')->willReturn(1000);

        $request = new MaintenanceRequest();
        (new CaptureRequestOperationProcessor())->process($request, new CaptureRequestContext($order, $payment, 'txn_ref_123'));

        $this->assertSame('capture-18-17', $request->operation_id);
    }
}
