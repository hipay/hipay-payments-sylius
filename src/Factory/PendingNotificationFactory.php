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
use HiPay\SyliusHiPayPlugin\Entity\PendingNotification;
use HiPay\SyliusHiPayPlugin\Entity\PendingNotificationInterface;

final class PendingNotificationFactory implements PendingNotificationFactoryInterface
{
    public function createFromHipayNotification(
        string $eventId,
        int $status,
        int $priority,
        array $payload,
        DateTimeInterface $availableAt,
        ?string $transactionReference,
    ): PendingNotificationInterface {
        $pending = new PendingNotification();
        $pending->setEventId($eventId);
        $pending->setStatus($status);
        $pending->setPriority($priority);
        $pending->setPayload($payload);
        $pending->setAvailableAt($availableAt);
        $pending->setTransactionReference($transactionReference);

        return $pending;
    }
}
