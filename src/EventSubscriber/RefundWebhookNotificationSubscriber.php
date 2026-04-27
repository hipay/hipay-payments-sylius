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
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Payment\HiPayStatus;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\RefundPlugin\StateResolver\RefundPaymentCompletedStateApplierInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class RefundWebhookNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $refundPaymentRepository,
        private readonly RefundPaymentCompletedStateApplierInterface $refundPaymentCompletedStateApplier,
        private readonly HiPayLoggerInterface $hiPayLogger,
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
        $notification = $event->getNotification();

        if (!in_array((int) $notification['status'], [HiPayStatus::Refunded->value, HiPayStatus::PartiallyRefunded->value], true)) {
            return;
        }
        $payment = $event->getPayment();
        $order = $payment->getOrder();
        if (null === $order) {
            $this->hiPayLogger->warning('[Hipay][RefundWebhookNotificationSubscriber] No order found for payment', ['id' => $payment->getId()]);

            return;
        }
        // @phpstan-ignore-next-line
        $refundPayment = $this->refundPaymentRepository->findOneByOrder($order);
        if (null === $refundPayment) {
            $this->hiPayLogger->warning('[Hipay][RefundWebhookNotificationSubscriber] No refund payment found for order', ['id' => $order->getId()]);

            return;
        }
        $this->refundPaymentCompletedStateApplier->apply($refundPayment);
    }
}
