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
use HiPay\SyliusHiPayPlugin\Entity\SavedCardInterface;
use HiPay\SyliusHiPayPlugin\Event\AfterWebhookNotificationProcessedEvent;
use HiPay\SyliusHiPayPlugin\Factory\SavedCardFactoryInterface;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Payment\HiPayStatus;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Repository\SavedCardRepositoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Two-step saved card flow (HIPA15):
 *  1. Authorized (116) → card created with authorized=false
 *  2. Captured  (118)  → existing card set to authorized=true
 *
 * If the Authorized notification is missed, Captured acts as a resilient
 * fallback by creating the card directly with authorized=true.
 */
final class SavedCardWebhookNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SavedCardFactoryInterface $savedCardFactory,
        private readonly HiPayLoggerInterface $hiPayLogger,
        private readonly AccountProviderInterface $accountProvider,
        private readonly EntityManagerInterface $entityManager,
        private readonly SavedCardRepositoryInterface $savedCardRepository,
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

        match ($status) {
            HiPayStatus::Authorized->value => $this->handleAuthorized($event),
            HiPayStatus::Captured->value,
            HiPayStatus::PartiallyCaptured->value => $this->handleCaptured($event),
            default => null,
        };
    }

    /**
     * Step 1: on authorization, persist the card with authorized=false.
     */
    private function handleAuthorized(AfterWebhookNotificationProcessedEvent $event): void
    {
        $payment = $event->getPayment();

        if (!$this->isMultiUse($payment)) {
            return;
        }

        $this->initLogger($payment);

        $savedCard = $this->savedCardFactory->createNewFromPayment($payment);
        if (null === $savedCard) {
            $this->hiPayLogger->warning('[Hipay][SavedCard] Missing data to save card on authorization.');

            return;
        }

        $this->entityManager->persist($savedCard);
        $this->entityManager->flush();

        $this->hiPayLogger->info('[Hipay][SavedCard] Card saved (pending capture confirmation)', [
            'saved_card_id' => $savedCard->getId(),
            'customer_id' => $payment->getOrder()?->getCustomer()?->getId(),
        ]);
    }

    /**
     * Step 2: on capture, find the pending card and set authorized=true.
     * Fallback: if no card exists (missed authorization notification), create one directly as authorized.
     */
    private function handleCaptured(AfterWebhookNotificationProcessedEvent $event): void
    {
        $payment = $event->getPayment();

        if (!$this->isMultiUse($payment)) {
            return;
        }

        $this->initLogger($payment);

        $token = $payment->getDetails()['token'] ?? null;
        $customer = $payment->getOrder()?->getCustomer();
        if (null === $token || null === $customer) {
            $this->hiPayLogger->warning('[Hipay][SavedCard] Missing token or customer on capture.');

            return;
        }

        /** @var SavedCardInterface|null $savedCard */
        $savedCard = $this->savedCardRepository->findOneBy(['token' => $token, 'customer' => $customer]);

        if (null !== $savedCard) {
            if (!$savedCard->isAuthorized()) {
                $savedCard->setAuthorized(true);
                $this->entityManager->flush();

                $this->hiPayLogger->info('[Hipay][SavedCard] Card authorized after capture', [
                    'saved_card_id' => $savedCard->getId(),
                ]);
            }

            return;
        }

        // Fallback: authorization notification was missed, create the card as authorized
        $savedCard = $this->savedCardFactory->createNewFromPayment($payment);
        if (null === $savedCard) {
            $this->hiPayLogger->warning('[Hipay][SavedCard] Missing data to save card on capture (fallback).');

            return;
        }

        $savedCard->setAuthorized(true);
        $this->entityManager->persist($savedCard);
        $this->entityManager->flush();

        $this->hiPayLogger->info('[Hipay][SavedCard] Card saved and authorized on capture (fallback)', [
            'saved_card_id' => $savedCard->getId(),
            'customer_id' => $customer->getId(),
        ]);
    }

    private function isMultiUse(PaymentInterface $payment): bool
    {
        return 0 !== ($payment->getDetails()['multi_use'] ?? 0);
    }

    private function initLogger(PaymentInterface $payment): void
    {
        /** @var PaymentMethodInterface|null $paymentMethod */
        $paymentMethod = $payment->getMethod();
        if (null !== $paymentMethod) {
            $account = $this->accountProvider->getByPaymentMethod($paymentMethod);
            $this->hiPayLogger->setAccount($account);
        }
    }
}
