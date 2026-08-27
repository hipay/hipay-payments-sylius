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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Security;

use HiPay\SyliusHiPayPlugin\Entity\SavedCardInterface;
use HiPay\SyliusHiPayPlugin\Exception\InvalidPaymentPayloadException;
use HiPay\SyliusHiPayPlugin\Repository\SavedCardRepositoryInterface;
use HiPay\SyliusHiPayPlugin\Security\CardTokenOwnershipGuard;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Customer\Model\CustomerInterface;

/**
 * V2 — a browser-supplied card token must not be charged (nor persisted) if it is already
 * bound to another customer. Unknown tokens (freshly tokenised cards) and tokens owned by
 * the paying customer are allowed.
 */
final class CardTokenOwnershipGuardTest extends TestCase
{
    private const ATTACKER_ID = 1;

    private const VICTIM_ID = 2;

    public function testItRejectsATokenOwnedByAnotherCustomer(): void
    {
        $guard = $this->guard($this->savedCardOwnedBy(self::VICTIM_ID));

        $this->expectException(InvalidPaymentPayloadException::class);

        $guard->assertTokenBelongsToCustomer('tok_victim', $this->customer(self::ATTACKER_ID));
    }

    public function testItAcceptsATokenOwnedByThePayingCustomer(): void
    {
        $guard = $this->guard($this->savedCardOwnedBy(self::ATTACKER_ID));

        $guard->assertTokenBelongsToCustomer('tok_own', $this->customer(self::ATTACKER_ID));

        $this->expectNotToPerformAssertions();
    }

    public function testItAcceptsAnUnknownToken(): void
    {
        $guard = $this->guard(null);

        $guard->assertTokenBelongsToCustomer('tok_brand_new', $this->customer(self::ATTACKER_ID));

        $this->expectNotToPerformAssertions();
    }

    public function testItAcceptsAnEmptyTokenWithoutQueryingTheRepository(): void
    {
        $repository = $this->createMock(SavedCardRepositoryInterface::class);
        $repository->expects($this->never())->method('findOneBy');

        (new CardTokenOwnershipGuard($repository))
            ->assertTokenBelongsToCustomer('', $this->customer(self::ATTACKER_ID));
    }

    public function testItRejectsWhenTheStoredCardHasNoCustomer(): void
    {
        $guard = $this->guard($this->savedCardOwnedBy(null));

        $this->expectException(InvalidPaymentPayloadException::class);

        $guard->assertTokenBelongsToCustomer('tok_orphan', $this->customer(self::ATTACKER_ID));
    }

    public function testItRejectsWhenThePayingCustomerIsNull(): void
    {
        $guard = $this->guard($this->savedCardOwnedBy(self::VICTIM_ID));

        $this->expectException(InvalidPaymentPayloadException::class);

        $guard->assertTokenBelongsToCustomer('tok_victim', null);
    }

    private function guard(?SavedCardInterface $foundByToken): CardTokenOwnershipGuard
    {
        $repository = $this->createMock(SavedCardRepositoryInterface::class);
        $repository->method('findOneBy')->willReturn($foundByToken);

        return new CardTokenOwnershipGuard($repository);
    }

    private function savedCardOwnedBy(?int $customerId): SavedCardInterface
    {
        $savedCard = $this->createMock(SavedCardInterface::class);
        $savedCard->method('getCustomer')->willReturn(null === $customerId ? null : $this->customer($customerId));

        return $savedCard;
    }

    private function customer(int $id): CustomerInterface
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn($id);

        return $customer;
    }
}
