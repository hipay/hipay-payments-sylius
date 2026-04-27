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

namespace HiPay\SyliusHiPayPlugin\Entity;

use DateTimeInterface;
use HiPay\SyliusHiPayPlugin\Webhook\PendingNotificationState;
use Sylius\Resource\Model\TimestampableTrait;

/**
 * Persistent buffer row for an incoming HiPay webhook notification.
 *
 * The row is inserted synchronously by {@see \HiPay\SyliusHiPayPlugin\Webhook\Consumer}
 * with a scheduled `available_at` (NOW + buffer). The scheduler worker later claims
 * and processes batches in strict (priority ASC, id ASC) order so that, for a given
 * transaction, notifications are applied to the state machine in a deterministic
 * priority-based order regardless of HiPay's callback delivery sequence.
 *
 * This entity is intentionally NOT declared as a Sylius Resource: it is internal
 * infrastructure with no admin UI surface.
 */
class PendingNotification implements PendingNotificationInterface
{
    use TimestampableTrait;

    private ?int $id = null;

    private string $eventId = '';

    private ?string $transactionReference = null;

    private int $status = 0;

    private int $priority = 0;

    /** @var array<string, mixed> */
    private array $payload = [];

    private PendingNotificationState $state = PendingNotificationState::PENDING;

    private int $attempts = 0;

    private ?string $lastError = null;

    private DateTimeInterface $availableAt;

    private ?DateTimeInterface $claimedAt = null;

    private ?DateTimeInterface $processedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function setEventId(string $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function getTransactionReference(): ?string
    {
        return $this->transactionReference;
    }

    public function setTransactionReference(?string $transactionReference): void
    {
        $this->transactionReference = $transactionReference;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getState(): PendingNotificationState
    {
        return $this->state;
    }

    public function setState(PendingNotificationState $state): void
    {
        $this->state = $state;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): void
    {
        ++$this->attempts;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): void
    {
        $this->lastError = $lastError;
    }

    public function getAvailableAt(): DateTimeInterface
    {
        return $this->availableAt;
    }

    public function setAvailableAt(DateTimeInterface $availableAt): void
    {
        $this->availableAt = $availableAt;
    }

    public function getClaimedAt(): ?DateTimeInterface
    {
        return $this->claimedAt;
    }

    public function setClaimedAt(?DateTimeInterface $claimedAt): void
    {
        $this->claimedAt = $claimedAt;
    }

    public function getProcessedAt(): ?DateTimeInterface
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?DateTimeInterface $processedAt): void
    {
        $this->processedAt = $processedAt;
    }
}
