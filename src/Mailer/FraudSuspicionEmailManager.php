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

final readonly class FraudSuspicionEmailManager implements FraudSuspicionEmailManagerInterface
{
    public function __construct(
        private SenderInterface $emailSender,
        private HiPayLoggerInterface $logger,
    ) {
    }

    public function sendFraudSuspicionEmail(AccountInterface $account, PaymentInterface $payment): void
    {
        $this->logger->setAccount($account);

        /** @var OrderInterface|null $order */
        $order = $payment->getOrder();
        if (null === $order) {
            $this->logger->error('[HiPay][FraudSuspicionEmailManager] No order found for payment', [
                'payment_id' => $payment->getId(),
            ]);

            return;
        }

        $channel = $order->getChannel();
        if (null === $channel) {
            $this->logger->error('[HiPay][FraudSuspicionEmailManager] No channel found for order', [
                'order_id' => $order->getId(),
            ]);

            return;
        }

        $contactEmail = $payment->getOrder()?->getChannel()?->getContactEmail();
        if (empty($contactEmail)) {
            $this->logger->warning('[HiPay][FraudSuspicionEmailManager] No contact email configured on channel', [
                'channel' => $channel->getCode(),
                'order' => $order->getNumber(),
            ]);

            return;
        }

        $this->emailSender->send(
            Emails::FRAUD_SUSPICION,
            [$contactEmail],
            [
                'order' => $order,
                'channel' => $channel,
                'localeCode' => $order->getLocaleCode(),
            ],
        );

        $this->logger->info('[HiPay][FraudSuspicionEmailManager] Email sent', [
            'recipient' => $contactEmail,
            'order' => $order->getNumber(),
        ]);
    }
}
