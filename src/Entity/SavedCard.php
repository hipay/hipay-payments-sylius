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

use DateTimeImmutable;
use DateTimeInterface;
use Sylius\Component\Customer\Model\CustomerInterface;
use Sylius\Resource\Metadata\AsResource;

/**
 * Stores HiPay reusable token and non-sensitive card display data only (no full PAN, no CVC).
 */
#[AsResource(
    alias: 'sylius_hipay_plugin.saved_card',
    name: 'saved_card',
    pluralName: 'saved_cards',
    applicationName: 'sylius_hipay_plugin',
)]
class SavedCard implements SavedCardInterface
{
    private ?int $id = null;

    private ?CustomerInterface $customer = null;

    private string $token;

    private string $brand;

    private string $maskedPan;

    private string $expiryMonth;

    private string $expiryYear;

    private ?string $holder = null;

    private bool $authorized = false;

    private ?DateTimeInterface $createdAt = null;

    private ?DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getBrand(): string
    {
        return $this->brand;
    }

    public function setBrand(string $brand): void
    {
        $this->brand = $brand;
    }

    public function getMaskedPan(): string
    {
        return $this->maskedPan;
    }

    public function setMaskedPan(string $maskedPan): void
    {
        $this->maskedPan = $maskedPan;
    }

    public function getExpiryMonth(): string
    {
        return $this->expiryMonth;
    }

    public function setExpiryMonth(string $expiryMonth): void
    {
        $this->expiryMonth = $expiryMonth;
    }

    public function getExpiryYear(): string
    {
        return $this->expiryYear;
    }

    public function setExpiryYear(string $expiryYear): void
    {
        $this->expiryYear = $expiryYear;
    }

    public function getHolder(): ?string
    {
        return $this->holder;
    }

    public function setHolder(?string $holder): void
    {
        $this->holder = $holder;
    }

    public function isAuthorized(): bool
    {
        return $this->authorized;
    }

    public function setAuthorized(bool $authorized): void
    {
        $this->authorized = $authorized;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function isExpired(): bool
    {
        $monthRaw = trim($this->expiryMonth);
        $yearRaw = trim($this->expiryYear);
        if ('' === $monthRaw || '' === $yearRaw) {
            return true;
        }

        $month = (int) $monthRaw;
        $year = (int) $yearRaw;
        if ($month < 1 || $month > 12) {
            return true;
        }

        $today = new DateTimeImmutable('today');
        $lastDayOfExpiryMonth = (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))
            ->modify('last day of this month');

        return $today > $lastDayOfExpiryMonth;
    }
}
