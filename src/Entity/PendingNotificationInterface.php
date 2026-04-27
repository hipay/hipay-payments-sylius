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
use Sylius\Resource\Model\TimestampableInterface;

interface PendingNotificationInterface extends TimestampableInterface
{
    public function getId(): ?int;

    public function getEventId(): string;

    public function setEventId(string $eventId): void;

    public function getTransactionReference(): ?string;

    public function setTransactionReference(?string $transactionReference): void;

    public function getStatus(): int;

    public function setStatus(int $status): void;

    public function getPriority(): int;

    public function setPriority(int $priority): void;

    /** @return array<string, mixed> */
    public function getPayload(): array;

    /** @param array<string, mixed> $payload */
    public function setPayload(array $payload): void;

    public function getState(): PendingNotificationState;

    public function setState(PendingNotificationState $state): void;

    public function getAttempts(): int;

    public function incrementAttempts(): void;

    public function getLastError(): ?string;

    public function setLastError(?string $lastError): void;

    public function getAvailableAt(): DateTimeInterface;

    public function setAvailableAt(DateTimeInterface $availableAt): void;

    public function getClaimedAt(): ?DateTimeInterface;

    public function setClaimedAt(?DateTimeInterface $claimedAt): void;

    public function getProcessedAt(): ?DateTimeInterface;

    public function setProcessedAt(?DateTimeInterface $processedAt): void;
}
