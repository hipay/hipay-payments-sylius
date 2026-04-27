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

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Throwable;

/**
 * Cross-connection advisory lock on an order, using MySQL GET_LOCK/RELEASE_LOCK.
 *
 * **Problem (HIPASYLU001-108):**
 * When a HiPay payment fails, the browser after-pay redirect and the webhook
 * notification can hit the server at the same instant. Both threads process
 * the failure, both trigger Sylius's OrderProcessor, and both create a new
 * "new" Payment — resulting in duplicate payment forms on the repayment page.
 *
 * **Why advisory locks?**
 * Doctrine PESSIMISTIC_WRITE only works within a single DB transaction and
 * does not block threads on separate connections. MySQL GET_LOCK() is
 * server-wide: it blocks any connection that tries to acquire the same
 * named lock, regardless of transaction state. This guarantees that only
 * one thread processes a given order at a time.
 *
 * **Graceful degradation:**
 * Non-MySQL databases (SQLite in tests, PostgreSQL) do not support GET_LOCK.
 * When the lock cannot be acquired the callers proceed without it and rely
 * on the OrphanPaymentCanceller cleanup layers.
 *
 * **Usage:**
 * Both entry points (NotificationProcessor for webhooks and
 * HostedFieldsHttpResponseProvider for after-pay redirects) acquire this
 * lock before processing, and release it in a `finally` block.
 */
final readonly class OrderAdvisoryLock implements OrderAdvisoryLockInterface
{
    private const LOCK_PREFIX = 'hipay_order_';

    /** Seconds to wait before giving up on acquiring the lock. */
    private const LOCK_TIMEOUT = 3;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private HiPayLoggerInterface $logger,
    ) {
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

        $lockName = self::LOCK_PREFIX . $orderId;

        try {
            /** @var string|false $result */
            $result = $this->entityManager->getConnection()
                ->executeQuery('SELECT GET_LOCK(?, ?)', [$lockName, self::LOCK_TIMEOUT])
                ->fetchOne();

            if ('1' === (string) $result) {
                return $lockName;
            }

            $this->logger->warning('[HiPay][OrderAdvisoryLock] Could not acquire lock (timeout or contention)', [
                'lock_name' => $lockName,
                'order_number' => $order->getNumber(),
            ]);

            return null;
        } catch (Throwable) {
            // Non-MySQL databases (SQLite in tests) do not support GET_LOCK.
            return null;
        }
    }

    /**
     * Release a lock previously acquired by {@see acquire}.
     * No-op when $lockName is null (lock was never acquired).
     */
    public function release(?string $lockName): void
    {
        if (null === $lockName) {
            return;
        }

        try {
            $this->entityManager->getConnection()
                ->executeQuery('SELECT RELEASE_LOCK(?)', [$lockName]);
        } catch (Throwable) {
            // Best-effort: MySQL auto-releases on connection close anyway.
        }
    }
}
