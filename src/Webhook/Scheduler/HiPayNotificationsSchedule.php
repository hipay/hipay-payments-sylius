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

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Symfony Scheduler provider for the HiPay notifications worker.
 *
 * Every tick (default 30s), a {@see ProcessPendingBatchMessage} is dispatched
 * on the scheduler transport; the message is routed to the matching handler,
 * which then claims and processes a batch of pending notifications.
 *
 * The schedule name must match the worker command:
 *   bin/console messenger:consume scheduler_hipay_notifications
 */
#[AsSchedule('hipay_notifications')]
final readonly class HiPayNotificationsSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private int $intervalSeconds,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::every(
                sprintf('%d seconds', max(1, $this->intervalSeconds)),
                new ProcessPendingBatchMessage(),
            ));
    }
}
