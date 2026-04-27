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

use const DATE_ATOM;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Factory\PendingNotificationFactoryInterface;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Payment\HiPayStatus;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\TransactionProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Throwable;

/**
 * Synchronously persists an incoming HiPay webhook notification into
 * `hipay_pending_notification` with a buffered `available_at` window so the
 * scheduler worker can apply it in priority order.
 *
 * Goals:
 *   - Bound the webhook HTTP response time (no state machine / no event subscribers).
 *   - Absorb the race between the HiPay async notification and the Sylius
 *     checkout finalisation: by the time the worker picks the row up, the
 *     Payment row is expected to exist.
 *   - Dedupe on `event_id` = "{transaction_reference}_{status}": one row per
 *     (transaction, status) pair. HiPay always sends `attempt_id = "1"` for
 *     every notification of a transaction, so `attempt_id` cannot be used as a
 *     unique key. The UNIQUE constraint + INSERT-then-catch turns a retry of the
 *     same status into a silent no-op.
 *     NOTE: after a DBAL UniqueConstraintViolation Doctrine closes the EM; we
 *     call ManagerRegistry::resetManager() so that any wrapping Messenger
 *     middleware (DoctrineTransactionMiddleware) still has a usable connection.
 */
final readonly class Consumer implements ConsumerInterface
{
    public function __construct(
        private ManagerRegistry $registry,
        private HiPayLoggerInterface $logger,
        private ClockInterface $clock,
        private PendingNotificationFactoryInterface $pendingNotificationFactory,
        private TransactionProviderInterface $transactionProvider,
        private AccountProviderInterface $accountProvider,
        private int $bufferSeconds,
    ) {
    }

    public function consume(RemoteEvent $event): void
    {
        $payload = $event->getPayload();

        $transactionReferenceRaw = $payload['transaction_reference'] ?? null;
        $transactionReference = is_string($transactionReferenceRaw) && '' !== $transactionReferenceRaw
            ? $transactionReferenceRaw
            : null;

        $this->logger->setAccount($this->resolveAccount($transactionReference));

        $status = $this->extractStatus($payload);
        if (null === $status) {
            $this->logger->warning('[Hipay][Consumer] Discarded notification without status', [
                'event_id' => $event->getId(),
            ]);

            return;
        }

        // One row per (transaction, status): deduplicates HiPay retries where
        // attempt_id is always "1" for every notification of a transaction.
        $eventId = ($transactionReference ?? $event->getId()) . '_' . $status;

        $priority = HiPayStatus::getNotificationPriority($status);
        $availableAt = $this->clock->now()->modify(sprintf('+%d seconds', max(0, $this->bufferSeconds)));

        $pendingNotification = $this->pendingNotificationFactory->createFromHipayNotification(
            $eventId,
            $status,
            $priority,
            $payload,
            $availableAt,
            $transactionReference,
        );

        /** @var EntityManagerInterface $em */
        $em = $this->registry->getManager();

        try {
            $em->persist($pendingNotification);
            $em->flush();
        } catch (Throwable $e) {
            // After any flush failure, Doctrine closes the EM. Reset it before
            // returning so DoctrineTransactionMiddleware can still run cleanly.
            $this->registry->resetManager();

            if (!($e instanceof UniqueConstraintViolationException)) {
                throw $e;
            }

            // Duplicate event_id: HiPay retried the same attempt — the original
            // row is already queued or being processed.
            $this->logger->info('[Hipay][Consumer] Duplicate notification ignored', [
                'event_id' => $eventId,
                'transaction_reference' => $transactionReference,
            ]);

            return;
        }

        $this->logger->info('[Hipay][Consumer] Notification buffered', [
            'event_id' => $eventId,
            'transaction_reference' => $transactionReference,
            'status' => $status,
            'priority' => $priority,
            'available_at' => $availableAt->format(DATE_ATOM),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractStatus(array $payload): ?int
    {
        $status = $payload['status'] ?? null;
        if (is_int($status)) {
            return $status;
        }
        if (is_string($status) && ctype_digit($status)) {
            return (int) $status;
        }

        return null;
    }

    private function resolveAccount(?string $transactionReference): ?AccountInterface
    {
        if (null === $transactionReference) {
            return null;
        }

        /** @var ?PaymentInterface $payment */
        $payment = $this->transactionProvider->getByTransactionReference($transactionReference)?->getPayment();
        if (null === $payment) {
            return null;
        }

        return $this->accountProvider->getByPayment($payment);
    }
}
