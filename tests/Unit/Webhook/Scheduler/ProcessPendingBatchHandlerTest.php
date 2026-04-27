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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Webhook\Scheduler;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Entity\PendingNotification;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\TransactionProviderInterface;
use HiPay\SyliusHiPayPlugin\Repository\PendingNotificationRepository;
use HiPay\SyliusHiPayPlugin\Webhook\NotificationProcessorInterface;
use HiPay\SyliusHiPayPlugin\Webhook\PendingNotificationState;
use HiPay\SyliusHiPayPlugin\Webhook\Scheduler\ProcessPendingBatchHandler;
use HiPay\SyliusHiPayPlugin\Webhook\Scheduler\ProcessPendingBatchMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class ProcessPendingBatchHandlerTest extends TestCase
{
    private const BATCH_SIZE = 50;

    private const MAX_ATTEMPTS = 3;

    private const BASE_DELAY = 10;

    private const MAX_DELAY = 3600;

    private const STALLED_TIMEOUT = 600;

    private PendingNotificationRepository&MockObject $repository;

    private NotificationProcessorInterface&MockObject $processor;

    private EntityManagerInterface&MockObject $entityManager;

    private ProcessPendingBatchHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PendingNotificationRepository::class);
        $this->processor = $this->createMock(NotificationProcessorInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->handler = new ProcessPendingBatchHandler(
            $this->repository,
            $this->processor,
            $this->entityManager,
            $this->createMock(HiPayLoggerInterface::class),
            new MockClock(),
            $this->createMock(TransactionProviderInterface::class),
            $this->createMock(AccountProviderInterface::class),
            self::BATCH_SIZE,
            self::MAX_ATTEMPTS,
            self::BASE_DELAY,
            self::MAX_DELAY,
            self::STALLED_TIMEOUT,
        );
    }

    public function testEmptyBatchIsAShortCircuit(): void
    {
        $this->repository->expects(self::once())
            ->method('claimBatch')
            ->with(self::BATCH_SIZE, self::STALLED_TIMEOUT)
            ->willReturn([]);

        $this->processor->expects(self::never())->method('process');
        $this->entityManager->expects(self::never())->method('flush');

        ($this->handler)(new ProcessPendingBatchMessage());
    }

    public function testSuccessfullyProcessedRowIsMarkedCompleted(): void
    {
        $pending = $this->buildPending('evt-1');
        $this->repository->method('claimBatch')->willReturn([$pending]);

        $this->processor->expects(self::once())
            ->method('process')
            ->with('evt-1', $pending->getPayload());

        $this->entityManager->expects(self::once())->method('flush');

        ($this->handler)(new ProcessPendingBatchMessage());

        self::assertSame(PendingNotificationState::COMPLETED, $pending->getState());
        self::assertSame(1, $pending->getAttempts());
        self::assertNull($pending->getLastError());
        self::assertNotNull($pending->getProcessedAt());
    }

    public function testUnrecoverableExceptionMarksRowFailedImmediately(): void
    {
        $pending = $this->buildPending('evt-2');
        $this->repository->method('claimBatch')->willReturn([$pending]);

        $this->processor->method('process')->willThrowException(
            new UnrecoverableMessageHandlingException('no account'),
        );

        $this->entityManager->expects(self::once())->method('flush');

        ($this->handler)(new ProcessPendingBatchMessage());

        self::assertSame(PendingNotificationState::FAILED, $pending->getState());
        self::assertStringContainsString('no account', (string) $pending->getLastError());
        self::assertNotNull($pending->getProcessedAt());
    }

    public function testTransientErrorBeforeMaxAttemptsReschedulesWithBackoff(): void
    {
        $pending = $this->buildPending('evt-3');
        $this->repository->method('claimBatch')->willReturn([$pending]);

        $this->processor->method('process')->willThrowException(
            new RuntimeException('No payment found for transaction reference'),
        );

        $this->entityManager->expects(self::once())->method('flush');

        $before = new DateTimeImmutable();
        ($this->handler)(new ProcessPendingBatchMessage());
        $after = new DateTimeImmutable();

        self::assertSame(PendingNotificationState::PENDING, $pending->getState());
        self::assertSame(1, $pending->getAttempts());
        self::assertNull($pending->getClaimedAt());
        self::assertStringContainsString('No payment found', (string) $pending->getLastError());

        // First retry: base delay, so available_at ≈ now + 10s
        $scheduledAt = $pending->getAvailableAt()->getTimestamp();
        self::assertGreaterThanOrEqual($before->getTimestamp() + self::BASE_DELAY - 1, $scheduledAt);
        self::assertLessThanOrEqual($after->getTimestamp() + self::BASE_DELAY + 1, $scheduledAt);
    }

    public function testTransientErrorAtMaxAttemptsMarksRowFailed(): void
    {
        $pending = $this->buildPending('evt-4');
        // Advance attempts so the *incremented* count reaches max.
        for ($i = 0; $i < self::MAX_ATTEMPTS - 1; ++$i) {
            $pending->incrementAttempts();
        }

        $this->repository->method('claimBatch')->willReturn([$pending]);
        $this->processor->method('process')->willThrowException(new RuntimeException('boom'));

        $this->entityManager->expects(self::once())->method('flush');

        ($this->handler)(new ProcessPendingBatchMessage());

        self::assertSame(PendingNotificationState::FAILED, $pending->getState());
        self::assertSame(self::MAX_ATTEMPTS, $pending->getAttempts());
    }

    public function testOneFailingRowDoesNotBlockTheOtherRowsInBatch(): void
    {
        $first = $this->buildPending('evt-good');
        $poison = $this->buildPending('evt-poison');
        $third = $this->buildPending('evt-also-good');

        $this->repository->method('claimBatch')->willReturn([$first, $poison, $third]);

        $this->processor->method('process')->willReturnCallback(
            static function (string $eventId): void {
                if ('evt-poison' === $eventId) {
                    throw new RuntimeException('kaboom');
                }
            },
        );

        ($this->handler)(new ProcessPendingBatchMessage());

        self::assertSame(PendingNotificationState::COMPLETED, $first->getState());
        self::assertSame(PendingNotificationState::PENDING, $poison->getState(), 'transient: rescheduled, not failed');
        self::assertSame(PendingNotificationState::COMPLETED, $third->getState());
    }

    private function buildPending(string $eventId): PendingNotification
    {
        $pending = new PendingNotification();
        $pending->setEventId($eventId);
        $pending->setStatus(118);
        $pending->setPriority(7);
        $pending->setPayload(['transaction_reference' => 'TXN-' . $eventId, 'status' => 118]);
        $pending->setAvailableAt(new DateTimeImmutable('-5 seconds'));
        $pending->setTransactionReference('TXN-' . $eventId);

        return $pending;
    }
}
