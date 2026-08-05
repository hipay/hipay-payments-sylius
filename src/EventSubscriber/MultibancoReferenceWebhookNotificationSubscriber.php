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
use HiPay\SyliusHiPayPlugin\Mailer\MultibancoReferenceEmailManagerInterface;
use HiPay\SyliusHiPayPlugin\Payment\HiPayStatus;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PluginPaymentProduct;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\GatewayProvider;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends the Multibanco reference email when a Hosted Page payment receives
 * the "Authorization Requested" (142) webhook notification.
 *
 * The reference data is embedded directly in the notification payload as
 * payment_reference[reference], payment_reference[amount], etc.
 */
final class MultibancoReferenceWebhookNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MultibancoReferenceEmailManagerInterface $multibancoReferenceEmailManager,
        private readonly AccountProviderInterface $accountProvider,
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
        /** @var PaymentInterface $payment */
        $payment = $event->getPayment();

        if (GatewayProvider::FACTORY_HOSTED_PAGE !== $payment->getMethod()?->getGatewayConfig()?->getFactoryName()) {
            return;
        }

        $notification = $event->getNotification();

        if (HiPayStatus::AuthorizationRequested->value !== (int) ($notification['status'] ?? null)) {
            return;
        }

        if (PluginPaymentProduct::MULTIBANCO->value !== ($notification['payment_product'] ?? null)) {
            return;
        }

        $account = $this->accountProvider->getByPayment($payment);
        if (null === $account) {
            $this->hiPayLogger->warning('[Hipay][MultibancoReferenceWebhookNotification] No account found for the payment.', [
                'payment_id' => $payment->getId(),
                'customer_id' => $payment->getOrder()?->getCustomer()?->getId(),
            ]);

            return;
        }

        $referenceToPay = $this->extractReferenceToPay($notification);

        $this->multibancoReferenceEmailManager->sendMultibancoReferenceEmail($account, $payment, $referenceToPay);
    }

    /**
     * Maps the flat payment_reference[*] notification keys to the camelCase
     * format expected by the email template (reference_to_pay.expirationDate, etc.).
     *
     * @param array<string, string|int|bool> $notification
     *
     * @return array<string, string|int|float>
     */
    private function extractReferenceToPay(array $notification): array
    {
        return array_filter([
            'reference' => (string) ($notification['payment_reference']['reference'] ?? ''),
            'amount' => isset($notification['payment_reference']['amount']) ? (float) $notification['payment_reference']['amount'] : null,
            'entity' => (string) ($notification['payment_reference']['entity'] ?? ''),
            'expirationDate' => (string) ($notification['payment_reference']['expiration_date'] ?? ''),
            'expirationDatetime' => (string) ($notification['payment_reference']['expiration_datetime'] ?? ''),
        ]);
    }
}
