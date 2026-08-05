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

use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Payment\OrderAdvisoryLock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * The lock guarantee is "only one thread processes a given order at a time".
 * These tests exercise that against a real Symfony Lock store
 * ({@see InMemoryStore}, keyed by resource name) so two OrderAdvisoryLock
 * instances genuinely contend — exactly as two web/worker processes would on
 * the shared `lock_keys` table in production. The DoctrineDbalStore swap is
 * configuration-only; Symfony's stores are independently tested upstream.
 */
final class OrderAdvisoryLockTest extends TestCase
{
    private HiPayLoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(HiPayLoggerInterface::class);
    }

    public function testItAcquiresAFreeLockAndReturnsItsResourceName(): void
    {
        $lock = new OrderAdvisoryLock(new LockFactory(new InMemoryStore()), $this->logger);

        self::assertSame('hipay_order_42', $lock->acquire($this->createOrder(42)));
    }

    public function testItReturnsNullWhenTheOrderHasNoId(): void
    {
        $factory = $this->createMock(LockFactory::class);
        $factory->expects($this->never())->method('createLock');

        $lock = new OrderAdvisoryLock($factory, $this->logger);

        self::assertNull($lock->acquire($this->createOrder(null)));
    }

    public function testASecondProcessCannotAcquireAHeldLockButCanOnceItIsReleased(): void
    {
        // One shared store == one shared `lock_keys` table; two OrderAdvisoryLock
        // instances == two processes. A MockClock makes process B's contended
        // wait advance instantly and deterministically (no real sleeping).
        $factory = new LockFactory(new InMemoryStore());
        $processA = new OrderAdvisoryLock($factory, $this->logger);
        $processB = new OrderAdvisoryLock($factory, $this->logger, 0.2, 0.05, new MockClock());

        $order = $this->createOrder(42);

        // Process A holds the lock.
        $lockName = $processA->acquire($order);
        self::assertSame('hipay_order_42', $lockName);

        // Process B cannot acquire it while A holds it.
        self::assertNull($processB->acquire($order), 'A held lock must not be acquirable by another process');

        // Once A releases, B can acquire it.
        $processA->release($lockName);
        self::assertSame('hipay_order_42', $processB->acquire($order));
    }

    public function testItDegradesGracefullyAndLogsWhenTheLockStoreIsUnavailable(): void
    {
        // Simulates the store being unusable (e.g. the lock_keys table cannot be
        // created): acquire must not throw, must return null, and must warn.
        $failingLock = $this->createMock(SharedLockInterface::class);
        $failingLock->method('acquire')->willThrowException(new RuntimeException('Lock store down'));
        $factory = $this->createMock(LockFactory::class);
        $factory->method('createLock')->willReturn($failingLock);

        $lock = new OrderAdvisoryLock($factory, $this->logger);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('Lock store unavailable'),
                $this->arrayHasKey('error'),
            );

        self::assertNull($lock->acquire($this->createOrder(42)));
    }

    public function testReleaseIsANoOpForNullOrUnknownLockNames(): void
    {
        $factory = $this->createMock(LockFactory::class);
        $lock = new OrderAdvisoryLock($factory, $this->logger);

        // Neither should error.
        $lock->release(null);
        $lock->release('hipay_order_never_acquired');

        $this->expectNotToPerformAssertions();
    }

    private function createOrder(?int $id): OrderInterface&MockObject
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getId')->willReturn($id);
        $order->method('getNumber')->willReturn(null === $id ? null : sprintf('%09d', $id));

        return $order;
    }
}
