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

use HiPay\SyliusHiPayPlugin\Event\AfterPaymentProcessedEvent;
use HiPay\SyliusHiPayPlugin\Mailer\MultibancoReferenceEmailManagerInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PluginPaymentProduct;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

final class MultibancoReferenceAfterPaymentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MultibancoReferenceEmailManagerInterface $multibancoReferenceEmailManager,
        private readonly AccountProviderInterface $accountProvider,
        private readonly DecoderInterface $serializer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterPaymentProcessedEvent::class => 'onAfterPaymentProcessedProcessed',
        ];
    }

    public function onAfterPaymentProcessedProcessed(AfterPaymentProcessedEvent $event): void
    {
        /** @var PaymentInterface $payment */
        $payment = $event->getPayment();
        if (PluginPaymentProduct::MULTIBANCO->value !== $payment->getMethod()?->getCode()) {
            return;
        }
        $reponseData = $event->getResponseData();
        /** @var array $referenceToPay */
        $referenceToPay = isset($reponseData['referenceToPay']) && is_string($reponseData['referenceToPay']) ? $this->serializer->decode($reponseData['referenceToPay'], 'json') : [];

        $account = $this->accountProvider->getByPayment($payment);
        if (null === $account) {
            return;
        }

        $this->multibancoReferenceEmailManager->sendMultibancoReferenceEmail($account, $payment, $referenceToPay);
    }
}
