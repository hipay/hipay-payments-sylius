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

use Sylius\Component\Core\Model\OrderInterface;

/**
 * Cross-connection advisory lock on an order.
 *
 * @see OrderAdvisoryLock for the MySQL GET_LOCK implementation
 */
interface OrderAdvisoryLockInterface
{
    /**
     * Acquire a named advisory lock for the given order.
     *
     * @return string|null The lock name if acquired (pass to {@see release}), null otherwise
     */
    public function acquire(OrderInterface $order): ?string;

    /**
     * Release a lock previously acquired by {@see acquire}.
     * No-op when $lockName is null.
     */
    public function release(?string $lockName): void;
}
