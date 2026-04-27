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

namespace HiPay\SyliusHiPayPlugin\Event;

use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after resolving the payment from the gateway notification and before
 * creating a payment request entity and applying Sylius payment transitions.
 */
final class BeforeWebhookNotificationProcessedEvent extends Event
{
    /**
     * @param array<string, string|int|bool> $notification
     */
    public function __construct(
        private readonly string $eventId,
        private readonly array $notification,
        private readonly PaymentInterface $payment,
    ) {
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    /**
     * @return array<string, string|int|bool>
     */
    public function getNotification(): array
    {
        return $this->notification;
    }

    public function getPayment(): PaymentInterface
    {
        return $this->payment;
    }
}
