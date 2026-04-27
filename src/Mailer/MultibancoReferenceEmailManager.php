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

namespace HiPay\SyliusHiPayPlugin\Mailer;

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;

final readonly class MultibancoReferenceEmailManager implements MultibancoReferenceEmailManagerInterface
{
    public function __construct(
        private SenderInterface $emailSender,
        private HiPayLoggerInterface $logger,
    ) {
    }

    public function sendMultibancoReferenceEmail(AccountInterface $account, PaymentInterface $payment, array $referenceToPay): void
    {
        $this->logger->setAccount($account);
        if ([] === $referenceToPay) {
            $this->logger->error('[HiPay][MultibancoReferenceEmailManager] No reference to pay found');

            return;
        }

        /** @var OrderInterface|null $order */
        $order = $payment->getOrder();
        if (null === $order) {
            $this->logger->error('[HiPay][MultibancoReferenceEmailManager] No order found for payment', [
                'payment_id' => $payment->getId(),
            ]);

            return;
        }

        $channel = $order->getChannel();
        if (null === $channel) {
            $this->logger->error('[HiPay][MultibancoReferenceEmailManager] No channel found for order', [
                'order' => $order->getNumber(),
            ]);

            return;
        }

        $customerEmail = $payment->getOrder()?->getCustomer()?->getEmail();
        if (empty($customerEmail)) {
            $this->logger->warning('[HiPay][MultibancoReferenceEmailManager] No customer email found', [
                'order' => $order->getNumber(),
            ]);

            return;
        }

        $this->emailSender->send(
            Emails::MULTIBANCO_REFERENCE,
            [$customerEmail],
            [
                'order' => $order,
                'channel' => $channel,
                'localeCode' => $order->getLocaleCode(),
                'reference_to_pay' => $referenceToPay,
            ],
        );

        $this->logger->info('[HiPay][MultibancoReferenceEmailManager] Email sent', [
            'recipient' => $customerEmail,
            'order' => $order->getNumber(),
        ]);
    }
}
