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

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Event\AfterWebhookNotificationProcessedEvent;
use HiPay\SyliusHiPayPlugin\Provider\GatewayProvider;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Stores the payment_product from the webhook notification into the payment details
 * so it can be displayed alongside the payment method name in the admin order view.
 *
 * Only applies to Hosted Page payments where the product is not known upfront.
 */
final class PaymentProductWebhookNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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
        /** @var PaymentInterface $payment */
        $payment = $event->getPayment();

        if (GatewayProvider::FACTORY_HOSTED_PAGE !== $payment->getMethod()?->getGatewayConfig()?->getFactoryName()) {
            return;
        }

        $notification = $event->getNotification();
        $paymentProduct = $notification['payment_product'] ?? null;
        if (!is_string($paymentProduct) || '' === $paymentProduct) {
            return;
        }

        $details = $payment->getDetails();
        if (($details['payment_product'] ?? null) === $paymentProduct) {
            return;
        }

        $details['payment_product'] = $paymentProduct;
        $payment->setDetails($details);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();
    }
}
