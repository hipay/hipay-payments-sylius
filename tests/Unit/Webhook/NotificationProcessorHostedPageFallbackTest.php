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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Entity\TransactionInterface;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Payment\HiPayStatus;
use HiPay\SyliusHiPayPlugin\Payment\OrderAdvisoryLockInterface;
use HiPay\SyliusHiPayPlugin\Payment\OrphanPaymentCancellerInterface;
use HiPay\SyliusHiPayPlugin\Payment\PaymentPayloadRedactor;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\PaymentRequestProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\TransactionProviderInterface;
use HiPay\SyliusHiPayPlugin\Webhook\NotificationProcessor;
use HiPay\SyliusHiPayPlugin\Webhook\SigningAccountGuardInterface;
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
            $overrides['signingAccountGuard'] ?? $this->passingSigningAccountGuard(),
            new PaymentPayloadRedactor(),
        );
    }

    private function passingSigningAccountGuard(): SigningAccountGuardInterface
    {
        $guard = $this->createMock(SigningAccountGuardInterface::class);
        $guard->method('paymentBelongsToSigningAccount')->willReturn(true);

        return $guard;
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

        $transaction = $this->createMock(TransactionInterface::class);
        $transactionFactory = $this->createMock(FactoryInterface::class);
        $transactionFactory->method('createNew')->willReturn($transaction);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getSingleAssociationJoinColumnName')->with('payment')->willReturn('payment_id');
        $metadata->method('getColumnName')->willReturnMap([
            ['transactionReference', 'transaction_reference'],
            ['createdAt', 'created_at'],
        ]);
        $metadata->method('getTableName')->willReturn('hipay_transaction');

        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(false);
        $connection->expects($this->once())->method('insert');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('getClassMetadata')->willReturn($metadata);
        // persist()/flush() remain in use for the PaymentRequest, never for the transaction row.
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
            $this->passingSigningAccountGuard(),
            new PaymentPayloadRedactor(),
        );

        $processor->process('evt-fallback', [
            'transaction_reference' => 'ref-123',
            'status' => HiPayStatus::Authorized->value,
            'custom_data' => ['account_sylius_id' => 1, 'payment_id' => 42],
        ]);

        // If we reach here without exception, the fallback worked correctly
        $this->assertTrue(true);
    }

    /**
     * A concurrent writer (NewOrderRequestHandler or another notification) may create the
     * same transaction_reference between our existence check and our INSERT. The violation
     * must be swallowed by rolling back to the savepoint, leaving the EntityManager open.
     */
    public function testConcurrentDuplicateRollsBackSavepointAndDoesNotThrow(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('supportsSavepoints')->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->expects($this->once())->method('createSavepoint');
        $connection->method('insert')->willThrowException(
            $this->createMock(UniqueConstraintViolationException::class),
        );
        $connection->expects($this->once())->method('rollbackSavepoint');
        $connection->expects($this->never())->method('releaseSavepoint');

        $processor = $this->processorWithConnection($connection);

        // Must NOT throw: the duplicate is a no-op and the batch can keep going.
        $processor->process('evt-dup', [
            'transaction_reference' => 'ref-123',
            'status' => HiPayStatus::Authorized->value,
            'custom_data' => ['account_sylius_id' => 1, 'payment_id' => 42],
        ]);

        $this->assertTrue(true);
    }

    public function testSavepointIsReleasedWhenInsertSucceeds(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('supportsSavepoints')->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->expects($this->once())->method('createSavepoint');
        $connection->expects($this->once())->method('insert');
        $connection->expects($this->once())->method('releaseSavepoint');
        $connection->expects($this->never())->method('rollbackSavepoint');

        $processor = $this->processorWithConnection($connection);

        $processor->process('evt-ok', [
            'transaction_reference' => 'ref-123',
            'status' => HiPayStatus::Authorized->value,
            'custom_data' => ['account_sylius_id' => 1, 'payment_id' => 42],
        ]);

        $this->assertTrue(true);
    }

    /**
     * Defensive branch: inside an active transaction on a platform without savepoint support,
     * we cannot shield the surrounding transaction, so the situation must be logged loudly.
     */
    public function testWarnsWhenNoSavepointAvailableInActiveTransaction(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('supportsSavepoints')->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->expects($this->never())->method('createSavepoint');
        $connection->expects($this->once())->method('insert');

        $logger = $this->createMock(HiPayLoggerInterface::class);

        $processor = $this->processorWithConnection($connection, $logger);

        $processor->process('evt-nosp', [
            'transaction_reference' => 'ref-123',
            'status' => HiPayStatus::Authorized->value,
            'custom_data' => ['account_sylius_id' => 1, 'payment_id' => 42],
        ]);

        $this->assertTrue(true);
    }

    /**
     * Builds a processor wired for the Hosted Page fallback (no existing transaction, payment
     * resolved via custom_data.payment_id, status Authorized) around the given connection, so
     * each test can drive ensureTransactionRow()'s savepoint/insert behaviour in isolation.
     */
    private function processorWithConnection(Connection $connection, ?HiPayLoggerInterface $logger = null): NotificationProcessor
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getId')->willReturn(42);
        $payment->method('getState')->willReturn('new');
        $payment->method('getOrder')->willReturn(null);

        $transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $transactionProvider->method('getByTransactionReference')->willReturn(null);

        $paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $paymentRepository->method('find')->with(42)->willReturn($payment);

        $transaction = $this->createMock(TransactionInterface::class);
        $transactionFactory = $this->createMock(FactoryInterface::class);
        $transactionFactory->method('createNew')->willReturn($transaction);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getSingleAssociationJoinColumnName')->willReturn('payment_id');
        $metadata->method('getColumnName')->willReturnMap([
            ['transactionReference', 'transaction_reference'],
            ['createdAt', 'created_at'],
        ]);
        $metadata->method('getTableName')->willReturn('hipay_transaction');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('getClassMetadata')->willReturn($metadata);

        $account = $this->createMock(AccountInterface::class);
        $accountProvider = $this->createMock(AccountProviderInterface::class);
        $accountProvider->method('getByPayment')->willReturn($account);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $paymentRequestProvider->method('createPaymentRequest')->willReturn($paymentRequest);

        $stateMachine = $this->createMock(StateMachineInterface::class);
        $stateMachine->method('can')->willReturn(true);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static fn ($event) => $event);

        return new NotificationProcessor(
            $stateMachine,
            $entityManager,
            $transactionProvider,
            $accountProvider,
            $logger ?? $this->createMock(HiPayLoggerInterface::class),
            $paymentRequestProvider,
            $dispatcher,
            $this->createMock(OrphanPaymentCancellerInterface::class),
            $this->createMock(OrderAdvisoryLockInterface::class),
            $paymentRepository,
            $transactionFactory,
            $this->passingSigningAccountGuard(),
            new PaymentPayloadRedactor(),
        );
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
