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

namespace HiPay\SyliusHiPayPlugin\Payment;

use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use function sprintf;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Throwable;

/**
 * Cross-connection advisory lock on an order, backed by the Symfony Lock
 * component.
 *
 * **Problem (HIPASYLU001-108):**
 * When a HiPay payment fails, the browser after-pay redirect and the webhook
 * notification can hit the server at the same instant. Both threads process
 * the failure, both trigger Sylius's OrderProcessor, and both create a new
 * "new" Payment — resulting in duplicate payment forms on the repayment page.
 *
 * **Why the Symfony Lock component (HIPASYLU003-47)?**
 * The previous implementation relied on MySQL's `GET_LOCK()`/`RELEASE_LOCK()`,
 * which PostgreSQL does not support — the lock silently degraded to a no-op on
 * any non-MySQL database. The Symfony Lock component with a {@see DoctrineDbalStore}
 * provides the same cross-connection, server-wide guarantee on every database
 * Sylius officially supports (MySQL, MariaDB and PostgreSQL >= 13.9), using a
 * shared `lock_keys` table created automatically on first use.
 *
 * **Usage:**
 * Both entry points (NotificationProcessor for webhooks and the
 * Hosted Fields/Hosted Page HTTP response providers for after-pay redirects),
 * as well as the manual capture handler, acquire this lock before processing
 * and release it in a `finally` block.
 */
final class OrderAdvisoryLock implements OrderAdvisoryLockInterface
{
    private const LOCK_PREFIX = 'hipay_order_';

    /** Default seconds to wait before giving up on acquiring the lock. */
    private const DEFAULT_ACQUIRE_TIMEOUT_SECONDS = 3.0;

    /** Default delay (seconds) between acquisition attempts while waiting for a contended lock. */
    private const DEFAULT_RETRY_INTERVAL_SECONDS = 0.1;

    /**
     * Seconds the lock stays valid. Acts as a safety net so a crashed thread
     * cannot hold the lock forever; well above the duration of any operation
     * guarded by it (including the synchronous HiPay capture HTTP call).
     */
    private const LOCK_TTL_SECONDS = 300.0;

    /**
     * Locks currently held by this instance, keyed by resource name so
     * {@see release} can find the object to release.
     *
     * @var array<string, LockInterface>
     */
    private array $locks = [];

    private readonly ClockInterface $clock;

    /**
     * @param float $acquireTimeoutSeconds  seconds to wait before giving up on a contended lock
     * @param float $retryIntervalSeconds   delay between acquisition attempts
     */
    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly HiPayLoggerInterface $logger,
        private readonly float $acquireTimeoutSeconds = self::DEFAULT_ACQUIRE_TIMEOUT_SECONDS,
        private readonly float $retryIntervalSeconds = self::DEFAULT_RETRY_INTERVAL_SECONDS,
        ?ClockInterface $clock = null,
    ) {
        // Default to a monotonic clock: the timeout is then immune to wall-clock
        // (NTP) adjustments mid-wait. Injectable so tests can drive it with a MockClock.
        $this->clock = $clock ?? new MonotonicClock();
    }

    /**
     * Acquire a named advisory lock for the given order.
     *
     * @return string|null The lock name if acquired (pass to {@see release}), null otherwise
     */
    public function acquire(OrderInterface $order): ?string
    {
        $orderId = $order->getId();
        if (null === $orderId) {
            return null;
        }

        $resource = self::LOCK_PREFIX . $orderId;
        $lock = $this->lockFactory->createLock($resource, self::LOCK_TTL_SECONDS);

        $deadline = $this->clock->now()->modify(sprintf('+%d microseconds', (int) ($this->acquireTimeoutSeconds * 1_000_000)));
        do {
            try {
                $acquired = $lock->acquire();
            } catch (Throwable $e) {
                // The lock store is unavailable (e.g. the lock_keys table cannot
                // be created). Degrade gracefully and rely on the
                // OrphanPaymentCanceller cleanup layers, as before.
                $this->logger->warning('[HiPay][OrderAdvisoryLock] Lock store unavailable, proceeding without lock', [
                    'resource' => $resource,
                    'order_number' => $order->getNumber(),
                    'error' => $e->getMessage(),
                ]);

                return null;
            }

            if ($acquired) {
                $this->locks[$resource] = $lock;

                return $resource;
            }

            $this->clock->sleep($this->retryIntervalSeconds);
        } while ($this->clock->now() < $deadline);

        $this->logger->warning('[HiPay][OrderAdvisoryLock] Could not acquire lock (timeout or contention)', [
            'resource' => $resource,
            'order_number' => $order->getNumber(),
        ]);

        return null;
    }

    /**
     * Release a lock previously acquired by {@see acquire}.
     * No-op when $lockName is null (lock was never acquired) or unknown.
     */
    public function release(?string $lockName): void
    {
        if (null === $lockName) {
            return;
        }

        $lock = $this->locks[$lockName] ?? null;
        if (null === $lock) {
            return;
        }

        unset($this->locks[$lockName]);

        try {
            $lock->release();
        } catch (Throwable) {
            // Best-effort: the lock auto-expires after its TTL anyway.
        }
    }
}
