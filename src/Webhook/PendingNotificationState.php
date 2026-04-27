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

/**
 * Lifecycle states of a {@see \HiPay\SyliusHiPayPlugin\Entity\PendingNotification} row.
 *
 * PENDING     -> Waiting for its buffer window (available_at) to elapse.
 * PROCESSING  -> Claimed by the scheduler worker (FOR UPDATE SKIP LOCKED).
 * COMPLETED   -> Successfully processed (payment transitioned, no-op informational, or permanent reject).
 * FAILED      -> Exhausted retry attempts; kept in table for operational inspection.
 */
enum PendingNotificationState: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
