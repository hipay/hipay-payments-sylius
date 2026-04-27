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

namespace HiPay\SyliusHiPayPlugin\Webhook;

use DateTimeInterface;

interface NotificationProcessorInterface
{
    /**
     * @param string                  $eventId                Event id from the webhook (RemoteEvent::getId())
     * @param array<string, mixed>    $notification           The decoded JSON payload
     * @param DateTimeInterface|null  $notificationReceivedAt When the webhook was originally received; used as PaymentRequest created_at so the admin list reflects reception order rather than batch-processing order
     */
    public function process(string $eventId, array $notification, ?DateTimeInterface $notificationReceivedAt = null): void;
}
