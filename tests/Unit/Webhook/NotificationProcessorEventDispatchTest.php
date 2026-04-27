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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Webhook;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Entity\TransactionInterface;
use HiPay\SyliusHiPayPlugin\Event\AfterWebhookNotificationProcessedEvent;
use HiPay\SyliusHiPayPlugin\Event\BeforeWebhookNotificationProcessedEvent;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Payment\HiPayStatus;
use HiPay\SyliusHiPayPlugin\Payment\OrderAdvisoryLockInterface;
use HiPay\SyliusHiPayPlugin\Payment\OrphanPaymentCancellerInterface;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\PaymentRequestProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\TransactionProviderInterface;
use HiPay\SyliusHiPayPlugin\Webhook\NotificationProcessor;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class NotificationProcessorEventDispatchTest extends TestCase
{
    public function testDispatchesBeforeAndAfterEventsWhenTransitionApplied(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($this->createMock(PaymentMethodInterface::class));
        $payment->method('getId')->willReturn(99);
        $payment->method('getState')->willReturn('new');

        $transaction = $this->createMock(TransactionInterface::class);
        $transaction->method('getPayment')->willReturn($payment);

        $transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $transactionProvider->method('getByTransactionReference')->with('ref-xyz')->willReturn($transaction);

        $account = $this->createMock(AccountInterface::class);
        $accountProvider = $this->createMock(AccountProviderInterface::class);
        $accountProvider->method('getByPayment')->with($payment)->willReturn($account);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $paymentRequestProvider->method('createPaymentRequest')->willReturn($paymentRequest);
        $paymentRequestProvider->expects($this->once())->method('setProcessState')->with($paymentRequest);
        $paymentRequestProvider->expects($this->once())->method('setCompleteState')->with($paymentRequest);

        $stateMachine = $this->createMock(StateMachineInterface::class);
        $stateMachine->method('can')->willReturn(true);
        $stateMachine->expects($this->once())->method('apply')->with(
            $payment,
            PaymentTransitions::GRAPH,
            PaymentTransitions::TRANSITION_AUTHORIZE,
        );

        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->expects($this->once())->method('persist')->with($paymentRequest);
        $entityManager->expects($this->once())->method('flush');

        $logger = $this->createMock(HiPayLoggerInterface::class);
        $logger->expects($this->once())->method('setAccount')->with($account);
        $logger->expects($this->once())->method('info');

        $dispatched = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))->method('dispatch')->willReturnCallback(function ($event) use (&$dispatched) {
            $dispatched[] = $event;

            return $event;
        });

        $processor = new NotificationProcessor(
            $stateMachine,
            $entityManager,
            $transactionProvider,
            $accountProvider,
            $logger,
            $paymentRequestProvider,
            $dispatcher,
            $this->createMock(OrphanPaymentCancellerInterface::class),
            $this->createMock(OrderAdvisoryLockInterface::class),
        );

        $processor->process('evt-test', [
            'transaction_reference' => 'ref-xyz',
            'status' => HiPayStatus::Authorized->value,
        ]);

        $this->assertInstanceOf(BeforeWebhookNotificationProcessedEvent::class, $dispatched[0]);
        $this->assertInstanceOf(AfterWebhookNotificationProcessedEvent::class, $dispatched[1]);
    }

    public function testDispatchesOnlyBeforeEventWhenTransitionCannotBeApplied(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($this->createMock(PaymentMethodInterface::class));
        $payment->method('getId')->willReturn(1);

        $transaction = $this->createMock(TransactionInterface::class);
        $transaction->method('getPayment')->willReturn($payment);

        $transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $transactionProvider->method('getByTransactionReference')->willReturn($transaction);

        $account = $this->createMock(AccountInterface::class);
        $accountProvider = $this->createMock(AccountProviderInterface::class);
        $accountProvider->method('getByPayment')->willReturn($account);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $paymentRequestProvider->method('createPaymentRequest')->willReturn($paymentRequest);
        $paymentRequestProvider->expects($this->once())->method('setProcessState');
        $paymentRequestProvider->expects($this->once())->method('setCancelState')->with($paymentRequest);

        $stateMachine = $this->createMock(StateMachineInterface::class);
        $stateMachine->method('can')->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $logger = $this->createMock(HiPayLoggerInterface::class);
        $logger->method('setAccount');
        $logger->expects($this->once())->method('error');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())->method('dispatch')->with(
            $this->isInstanceOf(BeforeWebhookNotificationProcessedEvent::class),
        )->willReturnCallback(static fn ($event) => $event);

        $processor = new NotificationProcessor(
            $stateMachine,
            $entityManager,
            $transactionProvider,
            $accountProvider,
            $logger,
            $paymentRequestProvider,
            $dispatcher,
            $this->createMock(OrphanPaymentCancellerInterface::class),
            $this->createMock(OrderAdvisoryLockInterface::class),
        );

        // Status 113 maps to fail; can() returns false → error, cancelled PaymentRequest, no After event
        $processor->process('evt-blocked', [
            'transaction_reference' => 'ref-blocked',
            'status' => HiPayStatus::Refused->value,
        ]);
    }

    public function testInformationalNotificationCompletesPaymentRequestAndDispatchesAfterEvent(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($this->createMock(PaymentMethodInterface::class));
        $payment->method('getId')->willReturn(42);
        $payment->method('getState')->willReturn('processing');

        $transaction = $this->createMock(TransactionInterface::class);
        $transaction->method('getPayment')->willReturn($payment);

        $transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $transactionProvider->method('getByTransactionReference')->willReturn($transaction);

        $account = $this->createMock(AccountInterface::class);
        $accountProvider = $this->createMock(AccountProviderInterface::class);
        $accountProvider->method('getByPayment')->willReturn($account);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $paymentRequestProvider->method('createPaymentRequest')->willReturn($paymentRequest);
        $paymentRequestProvider->expects($this->once())->method('setProcessState');
        $paymentRequestProvider->expects($this->once())->method('setCompleteState')->with($paymentRequest);
        $paymentRequestProvider->expects($this->never())->method('setCancelState');

        $stateMachine = $this->createMock(StateMachineInterface::class);
        $stateMachine->expects($this->never())->method('apply');

        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $logger = $this->createMock(HiPayLoggerInterface::class);
        $logger->method('setAccount');
        $logger->expects($this->once())->method('info');
        $logger->expects($this->never())->method('error');

        $dispatched = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))->method('dispatch')->willReturnCallback(function ($event) use (&$dispatched) {
            $dispatched[] = $event;

            return $event;
        });

        $processor = new NotificationProcessor(
            $stateMachine,
            $entityManager,
            $transactionProvider,
            $accountProvider,
            $logger,
            $paymentRequestProvider,
            $dispatcher,
            $this->createMock(OrphanPaymentCancellerInterface::class),
            $this->createMock(OrderAdvisoryLockInterface::class),
        );

        // Status 101 (TransactionCreated) is informational → completed PaymentRequest, no transition
        $processor->process('evt-info', [
            'transaction_reference' => 'ref-info',
            'status' => HiPayStatus::TransactionCreated->value,
        ]);

        $this->assertInstanceOf(BeforeWebhookNotificationProcessedEvent::class, $dispatched[0]);
        $this->assertInstanceOf(AfterWebhookNotificationProcessedEvent::class, $dispatched[1]);
    }
}
