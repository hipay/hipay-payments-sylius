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

namespace HiPay\SyliusHiPayPlugin\Webhook;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Event\AfterWebhookNotificationProcessedEvent;
use HiPay\SyliusHiPayPlugin\Event\BeforeWebhookNotificationProcessedEvent;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Payment\HiPayStatus;
use HiPay\SyliusHiPayPlugin\Payment\OrderAdvisoryLockInterface;
use HiPay\SyliusHiPayPlugin\Payment\OrphanPaymentCancellerInterface;
use HiPay\SyliusHiPayPlugin\Payment\PaymentState;
use HiPay\SyliusHiPayPlugin\Payment\PaymentTransitions as HiPayPaymentTransitions;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\PaymentRequestProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\TransactionProviderInterface;
use RuntimeException;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class NotificationProcessor implements NotificationProcessorInterface
{
    public function __construct(
        private readonly StateMachineInterface $stateMachine,
        private readonly EntityManagerInterface $entityManager,
        private readonly TransactionProviderInterface $hiPayTransactionProvider,
        private readonly AccountProviderInterface $accountProvider,
        private readonly HiPayLoggerInterface $logger,
        private readonly PaymentRequestProviderInterface $paymentRequestProvider,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly OrphanPaymentCancellerInterface $orphanPaymentCanceller,
        private readonly OrderAdvisoryLockInterface $orderAdvisoryLock,
    ) {
    }

    /**
     * @param array<'transaction_reference'|'status'|string, string|int|bool> $notification
     */
    public function process(string $eventId, array $notification, ?DateTimeInterface $notificationReceivedAt = null): void
    {
        /** @var string $transactionReference */
        $transactionReference = $notification['transaction_reference'];
        $status = (int) $notification['status'];

        /** @var ?PaymentInterface $payment */
        $payment = $this->hiPayTransactionProvider->getByTransactionReference($transactionReference)?->getPayment();
        $paymentMethod = $payment?->getMethod();
        if (null === $payment || null === $paymentMethod) {
            $this->logger->error('[Hipay][NotificationProcessor] No payment found for transaction reference', [
                'event_id' => $eventId,
                'transaction_reference' => $transactionReference,
            ]);

            throw new RuntimeException('No payment found for transaction reference');
        }

        // Advisory lock: prevent concurrent processing of the same order
        // by webhook and after-pay redirect. See HIPASYLU001-108.
        $order = $payment->getOrder();
        $lockName = null !== $order ? $this->orderAdvisoryLock->acquire($order) : null;

        try {
            $this->eventDispatcher->dispatch(new BeforeWebhookNotificationProcessedEvent($eventId, $notification, $payment));

            $account = $this->accountProvider->getByPayment($payment);
            if (null === $account) {
                $this->logger->error('[Hipay][NotificationProcessor] No account found for payment', [
                    'event_id' => $eventId,
                    'payment_id' => $payment->getId(),
                ]);

                throw new UnrecoverableMessageHandlingException('No account found for payment');
            }

            $this->logger->setAccount($account);

            $action = HiPayStatus::getPaymentRequestAction($status) ?? PaymentRequestInterface::ACTION_STATUS;
            $paymentRequest = $this->paymentRequestProvider->createPaymentRequest($payment, $paymentMethod, $action, $this->sanitizePayloadForStorage($notification));
            if (null !== $notificationReceivedAt) {
                $paymentRequest->setCreatedAt($notificationReceivedAt);
            }
            $this->entityManager->persist($paymentRequest);
            $this->paymentRequestProvider->setProcessState($paymentRequest);

            $this->unholdPaymentIfNeeded($payment);

            $transition = HiPayStatus::getSyliusTransition($status);

            // Informational or no-op: no state change expected, mark as completed
            if (null === $transition) {
                $this->logger->info('[Hipay][NotificationProcessor] Informational notification processed (no transition)', [
                    'event_id' => $eventId,
                    'status' => $status,
                    'payment_id' => $payment->getId(),
                ]);

                $paymentRequest->setResponseData(['message' => 'Informational notification processed']);
                $this->paymentRequestProvider->setCompleteState($paymentRequest);
                $this->entityManager->flush();

                $this->eventDispatcher->dispatch(new AfterWebhookNotificationProcessedEvent($eventId, $notification, $payment));

                return;
            }

            // Transition expected but not applicable: state machine inconsistency
            if (false === $this->stateMachine->can($payment, PaymentTransitions::GRAPH, $transition)) {
                $this->logger->error('[Hipay][NotificationProcessor] Cannot apply transition for payment', [
                    'event_id' => $eventId,
                    'transition' => $transition,
                    'payment_id' => $payment->getId(),
                    'status' => $status,
                ]);

                $paymentRequest->setResponseData(['message' => 'Cannot apply transition for payment']);
                $this->paymentRequestProvider->setCancelState($paymentRequest);
                $this->entityManager->flush();

                return;
            }

            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, $transition);
            $this->logger->info('[Hipay][NotificationProcessor] Applying Sylius transition from HiPay notification', [
                'event_id' => $eventId,
                'transition' => $transition,
                'status' => $status,
                'payment_id' => $payment->getId(),
            ]);

            $paymentRequest->setResponseData(['message' => 'Applying Sylius transition from HiPay notification']);
            $this->paymentRequestProvider->setCompleteState($paymentRequest);
            $this->entityManager->flush();

            // After a state-changing notification (especially `fail`), Sylius's
            // OrderProcessor may have created a new "new" Payment for the retry.
            // If the concurrent after-pay redirect did the same, the order now has
            // duplicate "new" payments. Clean them up so the repayment page shows
            // a single payment form. See HIPASYLU001-108.
            $order = $payment->getOrder();
            if (null !== $order) {
                $this->orphanPaymentCanceller->cancelOrphanPayments($order);
            }

            $this->eventDispatcher->dispatch(new AfterWebhookNotificationProcessedEvent($eventId, $notification, $payment));
        } finally {
            $this->orderAdvisoryLock->release($lockName);
        }
    }

    private function sanitizePayloadForStorage(array $notification): array
    {
        $sanitized = $notification;

        // Remove CVC result at root level
        unset($sanitized['cvc_result']);

        if (isset($sanitized['payment_method']) && is_array($sanitized['payment_method'])) {
            $paymentMethod = $sanitized['payment_method'];
            unset($paymentMethod['token'], $paymentMethod['card_id'], $paymentMethod['pan'], $paymentMethod['cvc'], $paymentMethod['cvc_result']);
            $sanitized['payment_method'] = $paymentMethod;
        }

        return $sanitized;
    }

    public function unholdPaymentIfNeeded(PaymentInterface $payment): void
    {
        if (
            PaymentState::ON_HOLD === $payment->getState() &&
            $this->stateMachine->can($payment, PaymentTransitions::GRAPH, HiPayPaymentTransitions::UNHOLD)
        ) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, HiPayPaymentTransitions::UNHOLD);
        }
    }
}
