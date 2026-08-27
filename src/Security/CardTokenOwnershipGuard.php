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

namespace HiPay\SyliusHiPayPlugin\Security;

use HiPay\SyliusHiPayPlugin\Entity\SavedCardInterface;
use HiPay\SyliusHiPayPlugin\Exception\InvalidPaymentPayloadException;
use HiPay\SyliusHiPayPlugin\Repository\SavedCardRepositoryInterface;
use Sylius\Component\Customer\Model\CustomerInterface;

final class CardTokenOwnershipGuard implements CardTokenOwnershipGuardInterface
{
    public function __construct(
        private readonly SavedCardRepositoryInterface $savedCardRepository,
    ) {
    }

    public function assertTokenBelongsToCustomer(string $token, ?CustomerInterface $customer): void
    {
        if ('' === $token) {
            return;
        }

        /** @var SavedCardInterface|null $owner */
        $owner = $this->savedCardRepository->findOneBy(['token' => $token]);
        if (null === $owner) {
            return;
        }

        $ownerCustomerId = $owner->getCustomer()?->getId();
        $customerId = $customer?->getId();

        if (null === $ownerCustomerId || null === $customerId || $ownerCustomerId !== $customerId) {
            throw new InvalidPaymentPayloadException('Card token does not belong to this customer');
        }
    }
}
