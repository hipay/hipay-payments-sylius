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
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Payment\HiPayStatus;
use HiPay\SyliusHiPayPlugin\Payment\OrderAdvisoryLockInterface;
use HiPay\SyliusHiPayPlugin\Payment\OrphanPaymentCancellerInterface;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\PaymentRequestProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\TransactionProviderInterface;
use HiPay\SyliusHiPayPlugin\Webhook\NotificationProcessor;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class NotificationProcessorHostedPageFallbackTest extends TestCase
{
    /**
     * Creates a NotificationProcessor with all required dependencies.
     *
     * @param array<string, mixed> $overrides Allows replacing individual dependencies.
     */
    private function createProcessor(array $overrides = []): NotificationProcessor
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);

        $entityManager = $overrides['entityManager'] ?? $this->createMock(EntityManagerInterface::class);
        if (!isset($overrides['entityManager'])) {
            $entityManager->method('getConnection')->willReturn($connection);
        }

        return new NotificationProcessor(
            $overrides['stateMachine'] ?? $this->createMock(StateMachineInterface::class),
            $entityManager,
            $overrides['transactionProvider'] ?? $this->createMock(TransactionProviderInterface::class),
            $overrides['accountProvider'] ?? $this->createMock(AccountProviderInterface::class),
            $overrides['logger'] ?? $this->createMock(HiPayLoggerInterface::class),
            $overrides['paymentRequestProvider'] ?? $this->createMock(PaymentRequestProviderInterface::class),
            $overrides['eventDispatcher'] ?? $this->createMock(EventDispatcherInterface::class),
            $this->createMock(OrphanPaymentCancellerInterface::class),
            $overrides['orderAdvisoryLock'] ?? $this->createMock(OrderAdvisoryLockInterface::class),
            $overrides['paymentRepository'] ?? $this->createMock(PaymentRepositoryInterface::class),
            $overrides['transactionFactory'] ?? $this->createMock(FactoryInterface::class),
        );
    }

    public function testFallsBackToCustomDataPaymentIdWhenNoTransactionExists(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getId')->willReturn(42);
        $payment->method('getState')->willReturn('new');
        $payment->method('getOrder')->willReturn(null);

        // No existing transaction for this reference
        $transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $transactionProvider->method('getByTransactionReference')->with('ref-123')->willReturn(null);

        // Payment found via repository using custom_data.payment_id
        $paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $paymentRepository->method('find')->with(42)->willReturn($payment);

        // A new TransactionInterface created and persisted
        $transaction = $this->createMock(TransactionInterface::class);
        $transactionFactory = $this->createMock(FactoryInterface::class);
        $transactionFactory->method('createNew')->willReturn($transaction);

        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        // persist() called once for the transaction, once for the paymentRequest
        $entityManager->expects($this->atLeastOnce())->method('persist');
        $entityManager->expects($this->atLeastOnce())->method('flush');

        $account = $this->createMock(AccountInterface::class);
        $accountProvider = $this->createMock(AccountProviderInterface::class);
        $accountProvider->method('getByPayment')->willReturn($account);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $paymentRequestProvider->method('createPaymentRequest')->willReturn($paymentRequest);

        $stateMachine = $this->createMock(StateMachineInterface::class);
        $stateMachine->method('can')->willReturn(true);

        $logger = $this->createMock(HiPayLoggerInterface::class);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static fn ($event) => $event);

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
            $paymentRepository,
            $transactionFactory,
        );

        $processor->process('evt-fallback', [
            'transaction_reference' => 'ref-123',
            'status' => HiPayStatus::Authorized->value,
            'custom_data' => ['account_sylius_id' => 1, 'payment_id' => 42],
        ]);

        // If we reach here without exception, the fallback worked correctly
        $this->assertTrue(true);
    }

    public function testThrowsWhenCustomDataMissingAndNoTransaction(): void
    {
        $transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $transactionProvider->method('getByTransactionReference')->willReturn(null);

        $processor = $this->createProcessor([
            'transactionProvider' => $transactionProvider,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No payment found for transaction reference');

        $processor->process('evt-missing', [
            'transaction_reference' => 'ref-no-data',
            'status' => HiPayStatus::Authorized->value,
            // No 'custom_data' field
        ]);
    }

    public function testThrowsWhenCustomDataHasNoPaymentIdAndNoTransaction(): void
    {
        $transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $transactionProvider->method('getByTransactionReference')->willReturn(null);

        $processor = $this->createProcessor([
            'transactionProvider' => $transactionProvider,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No payment found for transaction reference');

        $processor->process('evt-no-pid', [
            'transaction_reference' => 'ref-no-pid',
            'status' => HiPayStatus::Authorized->value,
            'custom_data' => ['account_sylius_id' => 1],
        ]);
    }

    public function testThrowsWhenPaymentNotFoundInRepositoryAndNoTransaction(): void
    {
        $transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $transactionProvider->method('getByTransactionReference')->willReturn(null);

        $paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $paymentRepository->method('find')->with(999)->willReturn(null);

        $processor = $this->createProcessor([
            'transactionProvider' => $transactionProvider,
            'paymentRepository' => $paymentRepository,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No payment found for transaction reference');

        $processor->process('evt-not-found', [
            'transaction_reference' => 'ref-not-found',
            'status' => HiPayStatus::Authorized->value,
            'custom_data' => ['payment_id' => 999],
        ]);
    }
}
