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

namespace HiPay\SyliusHiPayPlugin\Webhook\Scheduler;

use const DATE_ATOM;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Entity\PendingNotificationInterface;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\TransactionProviderInterface;
use HiPay\SyliusHiPayPlugin\Repository\PendingNotificationRepository;
use HiPay\SyliusHiPayPlugin\Webhook\NotificationProcessorInterface;
use HiPay\SyliusHiPayPlugin\Webhook\PendingNotificationState;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Throwable;

/**
 * Ticked by {@see HiPayNotificationsSchedule}: claims the next batch of
 * eligible `hipay_pending_notification` rows and runs each through
 * {@see NotificationProcessorInterface}.
 *
 * Error handling is per-row, not per-batch: one poisonous notification cannot
 * block the rest of the queue.
 *
 *   - Success (state machine transitioned, or informational no-op):
 *       -> row state = COMPLETED, processed_at = now.
 *   - Non-recoverable error (e.g. {@see UnrecoverableMessageHandlingException}
 *     thrown by NotificationProcessor when no Account resolves for the payment):
 *       -> row state = FAILED immediately, no retry.
 *   - Transient error (RuntimeException — typically "No payment found for
 *     transaction reference" when the checkout commit hasn't landed yet):
 *       -> attempts++, available_at pushed forward with exponential backoff,
 *       -> state back to PENDING until $maxAttempts is reached, then FAILED.
 */
#[AsMessageHandler]
final readonly class ProcessPendingBatchHandler
{
    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private PendingNotificationRepository $repository,
        private NotificationProcessorInterface $notificationProcessor,
        private EntityManagerInterface $entityManager,
        private HiPayLoggerInterface $logger,
        private ClockInterface $clock,
        private TransactionProviderInterface $transactionProvider,
        private AccountProviderInterface $accountProvider,
        private int $batchSize,
        private int $maxAttempts,
        private int $retryBaseDelaySeconds,
        private int $retryMaxDelaySeconds,
        private int $stalledClaimTimeoutSeconds,
    ) {
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    public function __invoke(ProcessPendingBatchMessage $message): void
    {
        $batch = $this->repository->claimBatch($this->batchSize, $this->stalledClaimTimeoutSeconds);
        if ([] === $batch) {
            return;
        }

        $this->logger->setAccount($this->resolveAccount($batch[0]->getTransactionReference()));
        $this->logger->debug('[Hipay][Scheduler] Batch claimed', [
            'count' => count($batch),
        ]);

        $lastAdjustedAt = null;
        foreach ($batch as $pending) {
            $adjustedAt = $this->resolveReceivedAt($pending->getCreatedAt(), $lastAdjustedAt);
            $lastAdjustedAt = $adjustedAt;
            $this->processOne($pending, $adjustedAt);
        }
    }

    private function processOne(PendingNotificationInterface $pending, DateTimeImmutable $receivedAt): void
    {
        $pending->incrementAttempts();

        try {
            $this->notificationProcessor->process(
                $pending->getEventId(),
                $pending->getPayload(),
                $receivedAt,
            );

            $pending->setState(PendingNotificationState::COMPLETED);
            $pending->setProcessedAt($this->clock->now());
            $pending->setLastError(null);
            $this->entityManager->flush();

            $this->logger->info('[Hipay][Scheduler] Notification processed', [
                'event_id' => $pending->getEventId(),
                'transaction_reference' => $pending->getTransactionReference(),
                'attempts' => $pending->getAttempts(),
            ]);
        } catch (UnrecoverableMessageHandlingException $e) {
            $this->markFailed($pending, $e, recoverable: false);
        } catch (Throwable $e) {
            if ($pending->getAttempts() >= $this->maxAttempts) {
                $this->markFailed($pending, $e, recoverable: true);

                return;
            }
            $this->rescheduleForRetry($pending, $e);
        }
    }

    private function markFailed(PendingNotificationInterface $pending, Throwable $error, bool $recoverable): void
    {
        $pending->setState(PendingNotificationState::FAILED);
        $pending->setProcessedAt($this->clock->now());
        $pending->setLastError($this->formatError($error));
        $this->entityManager->flush();

        $this->logger->error('[Hipay][Scheduler] Notification permanently failed', [
            'event_id' => $pending->getEventId(),
            'transaction_reference' => $pending->getTransactionReference(),
            'attempts' => $pending->getAttempts(),
            'recoverable_exception' => $recoverable,
            'error' => $error->getMessage(),
        ]);
    }

    private function rescheduleForRetry(PendingNotificationInterface $pending, Throwable $error): void
    {
        $delay = $this->computeBackoffDelay($pending->getAttempts());
        $nextAvailableAt = $this->clock->now()->modify(sprintf('+%d seconds', $delay));

        $pending->setState(PendingNotificationState::PENDING);
        $pending->setAvailableAt($nextAvailableAt);
        $pending->setClaimedAt(null);
        $pending->setLastError($this->formatError($error));
        $this->entityManager->flush();

        $this->logger->warning('[Hipay][Scheduler] Notification deferred for retry', [
            'event_id' => $pending->getEventId(),
            'transaction_reference' => $pending->getTransactionReference(),
            'attempts' => $pending->getAttempts(),
            'delay_seconds' => $delay,
            'next_available_at' => $nextAvailableAt->format(DATE_ATOM),
            'error' => $error->getMessage(),
        ]);
    }

    /**
     * Exponential backoff capped at $retryMaxDelaySeconds.
     * First retry (attempts=1) = base; subsequent doublings: base, 2*base, 4*base...
     */
    private function computeBackoffDelay(int $attempts): int
    {
        $exponent = max(0, $attempts - 1);
        $delay = $this->retryBaseDelaySeconds * (2 ** $exponent);

        return (int) min($delay, $this->retryMaxDelaySeconds);
    }

    /**
     * Returns the notification's received-at timestamp, bumped by +1 second only when it
     * would collide with the previous notification's adjusted time (same second or earlier).
     * Notifications at distinct seconds are untouched; only concurrent ones are offset,
     * making the conflict visible as a 1-second gap in the admin PaymentRequest list.
     */
    private function resolveReceivedAt(?DateTimeInterface $createdAt, ?DateTimeImmutable $lastAdjustedAt): DateTimeImmutable
    {
        $base = null !== $createdAt
            ? DateTimeImmutable::createFromInterface($createdAt)
            : $this->clock->now();

        if (null === $lastAdjustedAt || $base->getTimestamp() > $lastAdjustedAt->getTimestamp()) {
            return $base;
        }

        return $lastAdjustedAt->modify('+1 second');
    }

    private function formatError(Throwable $error): string
    {
        // Truncate long messages so `last_error` stays useful without bloating the row.
        $message = sprintf('%s: %s', $error::class, $error->getMessage());

        return mb_substr($message, 0, 1000);
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
