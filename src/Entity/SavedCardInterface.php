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

use Sylius\Component\Customer\Model\CustomerInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;

interface SavedCardInterface extends ResourceInterface, TimestampableInterface
{
    public function getCustomer(): ?CustomerInterface;

    public function setCustomer(?CustomerInterface $customer): void;

    public function getToken(): string;

    public function setToken(string $token): void;

    public function getBrand(): string;

    public function setBrand(string $brand): void;

    public function getMaskedPan(): string;

    public function setMaskedPan(string $maskedPan): void;

    public function getExpiryMonth(): string;

    public function setExpiryMonth(string $expiryMonth): void;

    public function getExpiryYear(): string;

    public function setExpiryYear(string $expiryYear): void;

    public function getHolder(): ?string;

    public function setHolder(?string $holder): void;

    public function isAuthorized(): bool;

    public function setAuthorized(bool $authorized): void;

    public function isExpired(): bool;
}
