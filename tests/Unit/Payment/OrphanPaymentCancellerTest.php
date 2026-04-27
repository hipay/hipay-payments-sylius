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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Payment;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Payment\OrphanPaymentCanceller;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Payment\PaymentTransitions;

/**
 * @covers \HiPay\SyliusHiPayPlugin\Payment\OrphanPaymentCanceller
 */
final class OrphanPaymentCancellerTest extends TestCase
{
    private StateMachineInterface&MockObject $stateMachine;

    private EntityManagerInterface&MockObject $entityManager;

    private HiPayLoggerInterface&MockObject $logger;

    private OrphanPaymentCanceller $canceller;

    protected function setUp(): void
    {
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(HiPayLoggerInterface::class);

        $this->canceller = new OrphanPaymentCanceller(
            $this->stateMachine,
            $this->entityManager,
            $this->logger,
        );
    }

    public function testDoesNothingWhenSingleNewHiPayPayment(): void
    {
        $payment = $this->createHiPayPayment(1, PaymentInterface::STATE_NEW);
        $order = $this->createOrderWithPayments([$payment]);

        $this->stateMachine->expects($this->never())->method('apply');
        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->canceller->cancelOrphanPayments($order);

        $this->assertSame(0, $result);
    }

    public function testDoesNothingWhenNoNewPayments(): void
    {
        $payment = $this->createHiPayPayment(1, PaymentInterface::STATE_FAILED);
        $order = $this->createOrderWithPayments([$payment]);

        $this->stateMachine->expects($this->never())->method('apply');

        $result = $this->canceller->cancelOrphanPayments($order);

        $this->assertSame(0, $result);
    }

    public function testCancelsOlderOrphanAndKeepsMostRecent(): void
    {
        $orphan = $this->createHiPayPayment(22, PaymentInterface::STATE_NEW);
        $keeper = $this->createHiPayPayment(23, PaymentInterface::STATE_NEW);
        $order = $this->createOrderWithPayments([$orphan, $keeper]);

        $this->stateMachine->method('can')->willReturn(true);

        // Only the orphan (lower ID) should be cancelled
        $this->stateMachine->expects($this->once())
            ->method('apply')
            ->with($orphan, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);

        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->canceller->cancelOrphanPayments($order);

        $this->assertSame(1, $result);
    }

    public function testCancelsMultipleOrphansKeepingOnlyMostRecent(): void
    {
        $orphan1 = $this->createHiPayPayment(10, PaymentInterface::STATE_NEW);
        $orphan2 = $this->createHiPayPayment(11, PaymentInterface::STATE_NEW);
        $keeper = $this->createHiPayPayment(12, PaymentInterface::STATE_NEW);
        $order = $this->createOrderWithPayments([$orphan1, $orphan2, $keeper]);

        $this->stateMachine->method('can')->willReturn(true);

        // Both orphans should be cancelled, keeper (id=12) is untouched
        $this->stateMachine->expects($this->exactly(2))
            ->method('apply');

        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->canceller->cancelOrphanPayments($order);

        $this->assertSame(2, $result);
    }

    public function testIgnoresNonHiPayPayments(): void
    {
        $hiPayPayment = $this->createHiPayPayment(1, PaymentInterface::STATE_NEW);
        $stripePayment = $this->createNonHiPayPayment(2, PaymentInterface::STATE_NEW);
        $order = $this->createOrderWithPayments([$hiPayPayment, $stripePayment]);

        // Only one HiPay "new" payment → no orphans
        $this->stateMachine->expects($this->never())->method('apply');

        $result = $this->canceller->cancelOrphanPayments($order);

        $this->assertSame(0, $result);
    }

    public function testSkipsOrphanWhenCancelTransitionNotAvailable(): void
    {
        $orphan = $this->createHiPayPayment(22, PaymentInterface::STATE_NEW);
        $keeper = $this->createHiPayPayment(23, PaymentInterface::STATE_NEW);
        $order = $this->createOrderWithPayments([$orphan, $keeper]);

        // State machine says cancel is not available for this orphan
        $this->stateMachine->method('can')->willReturn(false);
        $this->stateMachine->expects($this->never())->method('apply');

        $this->logger->expects($this->once())->method('warning');
        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->canceller->cancelOrphanPayments($order);

        $this->assertSame(0, $result);
    }

    public function testIgnoresFailedHiPayPayments(): void
    {
        $failed = $this->createHiPayPayment(21, PaymentInterface::STATE_FAILED);
        $newPayment = $this->createHiPayPayment(22, PaymentInterface::STATE_NEW);
        $order = $this->createOrderWithPayments([$failed, $newPayment]);

        // Only one "new" HiPay payment → nothing to cancel
        $this->stateMachine->expects($this->never())->method('apply');

        $result = $this->canceller->cancelOrphanPayments($order);

        $this->assertSame(0, $result);
    }

    private function createHiPayPayment(int $id, string $state): PaymentInterface&MockObject
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn('hipay_hosted_fields');

        $method = $this->createMock(PaymentMethodInterface::class);
        $method->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getId')->willReturn($id);
        $payment->method('getState')->willReturn($state);
        $payment->method('getMethod')->willReturn($method);

        return $payment;
    }

    private function createNonHiPayPayment(int $id, string $state): PaymentInterface&MockObject
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn('stripe');

        $method = $this->createMock(PaymentMethodInterface::class);
        $method->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getId')->willReturn($id);
        $payment->method('getState')->willReturn($state);
        $payment->method('getMethod')->willReturn($method);

        return $payment;
    }

    /**
     * @param PaymentInterface[] $payments
     */
    private function createOrderWithPayments(array $payments): OrderInterface&MockObject
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getPayments')->willReturn(new ArrayCollection($payments));
        $order->method('getNumber')->willReturn('000000001');

        return $order;
    }
}
