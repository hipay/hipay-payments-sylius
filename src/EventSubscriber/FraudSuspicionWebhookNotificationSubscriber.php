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

namespace HiPay\SyliusHiPayPlugin\EventSubscriber;

use HiPay\SyliusHiPayPlugin\Event\AfterWebhookNotificationProcessedEvent;
use HiPay\SyliusHiPayPlugin\Mailer\FraudSuspicionEmailManagerInterface;
use HiPay\SyliusHiPayPlugin\Payment\HiPayStatus;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends a fraud suspicion email to the shop manager when the webhook
 * delivers the "Authorized and Pending" (112) notification, indicating
 * the transaction was challenged by the HiPay Fraud Protection Service.
 */
final class FraudSuspicionWebhookNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly FraudSuspicionEmailManagerInterface $fraudSuspicionEmailManager,
        private readonly AccountProviderInterface $accountProvider,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterWebhookNotificationProcessedEvent::class => 'onAfterWebhookNotificationProcessed',
        ];
    }

    public function onAfterWebhookNotificationProcessed(AfterWebhookNotificationProcessedEvent $event): void
    {
        $status = (int) $event->getNotification()['status'];
        if (HiPayStatus::AuthorizedAndPending->value !== $status) {
            return;
        }

        /** @var PaymentInterface $payment */
        $payment = $event->getPayment();
        $account = $this->accountProvider->getByPayment($payment);
        if (null === $account) {
            return;
        }

        $this->fraudSuspicionEmailManager->sendFraudSuspicionEmail($account, $payment);
    }
}
