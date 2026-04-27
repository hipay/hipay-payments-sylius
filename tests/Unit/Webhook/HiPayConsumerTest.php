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

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use HiPay\SyliusHiPayPlugin\Entity\PendingNotification;
use HiPay\SyliusHiPayPlugin\Factory\PendingNotificationFactory;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\TransactionProviderInterface;
use HiPay\SyliusHiPayPlugin\Webhook\Consumer;
use HiPay\SyliusHiPayPlugin\Webhook\PendingNotificationState;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\RemoteEvent\RemoteEvent;

final class HiPayConsumerTest extends TestCase
{
    private ManagerRegistry&MockObject $registry;

    private EntityManagerInterface&MockObject $entityManager;

    private HiPayLoggerInterface&MockObject $logger;

    private MockClock $clock;

    private PendingNotificationFactory $pendingNotificationFactory;

    private TransactionProviderInterface&MockObject $transactionProvider;

    private AccountProviderInterface&MockObject $accountProvider;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->registry->method('getManager')->willReturn($this->entityManager);
        $this->logger = $this->createMock(HiPayLoggerInterface::class);
        $this->clock = new MockClock();
        $this->pendingNotificationFactory = new PendingNotificationFactory();
        $this->transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $this->accountProvider = $this->createMock(AccountProviderInterface::class);
    }

    private function createConsumer(): Consumer
    {
        return new Consumer(
            $this->registry,
            $this->logger,
            $this->clock,
            $this->pendingNotificationFactory,
            $this->transactionProvider,
            $this->accountProvider,
            60,
        );
    }

    public function testBuffersValidNotificationIntoPendingTable(): void
    {
        $eventId = '550e8400-e29b-41d4-a716-446655440000';
        $payload = [
            'notification_id' => $eventId,
            'transaction_reference' => 'TXN-REF-12345',
            'status' => 118,
        ];

        $captured = null;
        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function ($entity) use (&$captured): bool {
                $captured = $entity;

                return $entity instanceof PendingNotification;
            }));
        $this->entityManager->expects(self::once())->method('flush');

        $consumer = $this->createConsumer();
        $consumer->consume(new RemoteEvent('hipay.notification', $eventId, $payload));

        self::assertInstanceOf(PendingNotification::class, $captured);
        self::assertSame('TXN-REF-12345_118', $captured->getEventId());
        self::assertSame('TXN-REF-12345', $captured->getTransactionReference());
        self::assertSame(118, $captured->getStatus());
        self::assertSame(PendingNotificationState::PENDING, $captured->getState());
        self::assertSame(7, $captured->getPriority(), 'Status 118 (Captured) maps to priority 7 (Paid).');
        self::assertGreaterThanOrEqual(
            (new DateTimeImmutable('-2 seconds'))->modify('+60 seconds')->getTimestamp(),
            $captured->getAvailableAt()->getTimestamp(),
        );
    }

    public function testIgnoresDuplicateEventIdSilently(): void
    {
        $eventId = 'dup-1';
        $payload = ['transaction_reference' => 'TXN-1', 'status' => 116];

        $this->entityManager->method('persist');
        $this->entityManager->method('flush')->willThrowException(
            $this->createMock(UniqueConstraintViolationException::class),
        );
        $this->registry->expects(self::once())->method('resetManager');

        $this->logger->expects(self::once())
            ->method('info')
            ->with(self::stringContains('Duplicate notification ignored'), self::anything());

        $consumer = $this->createConsumer();
        $consumer->consume(new RemoteEvent('hipay.notification', $eventId, $payload));
    }

    public function testDropsPayloadWithoutStatus(): void
    {
        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $this->logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('Discarded notification without status'), self::anything());

        $consumer = $this->createConsumer();
        $consumer->consume(new RemoteEvent('hipay.notification', 'evt-1', ['transaction_reference' => 'TXN-1']));
    }

    public function testAcceptsNumericStringStatus(): void
    {
        $captured = null;
        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function ($entity) use (&$captured): bool {
                $captured = $entity;

                return true;
            }));
        $this->entityManager->method('flush');

        $consumer = $this->createConsumer();
        $consumer->consume(new RemoteEvent('hipay.notification', 'evt-2', [
            'transaction_reference' => 'TXN-2',
            'status' => '116',
        ]));

        self::assertInstanceOf(PendingNotification::class, $captured);
        self::assertSame(116, $captured->getStatus());
    }

    public function testMissingTransactionReferenceIsNullable(): void
    {
        $captured = null;
        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function ($entity) use (&$captured): bool {
                $captured = $entity;

                return true;
            }));
        $this->entityManager->method('flush');

        $consumer = $this->createConsumer();
        $consumer->consume(new RemoteEvent('hipay.notification', 'evt-3', ['status' => 118]));

        self::assertInstanceOf(PendingNotification::class, $captured);
        self::assertNull($captured->getTransactionReference());
    }
}
