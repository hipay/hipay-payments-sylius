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

namespace HiPay\SyliusHiPayPlugin\Factory;

use DateTimeInterface;
use HiPay\SyliusHiPayPlugin\Entity\PendingNotificationInterface;

interface PendingNotificationFactoryInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function createFromHipayNotification(
        string $eventId,
        int $status,
        int $priority,
        array $payload,
        DateTimeInterface $availableAt,
        ?string $transactionReference,
    ): PendingNotificationInterface;
}
