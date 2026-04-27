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

namespace HiPay\SyliusHiPayPlugin\Webhook\Scheduler;

/**
 * Marker message dispatched by {@see HiPayNotificationsSchedule} on every tick.
 *
 * The matching handler claims the next batch of pending notifications from the
 * `hipay_pending_notification` table — the message itself carries no state:
 * the batching policy (size, buffer, retry) is stored on the row.
 */
final class ProcessPendingBatchMessage
{
}
