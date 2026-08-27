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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Webhook;

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Webhook\SigningAccountGuard;
use PHPUnit\Framework\TestCase;

/**
 * a notification may only drive a payment whose account matches the account whose
 * passphrase signed it (identified by custom_data.account_sylius_id).
 */
final class SigningAccountGuardTest extends TestCase
{
    private SigningAccountGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new SigningAccountGuard();
    }

    public function testItAcceptsWhenThePaymentAccountMatchesTheSigningAccount(): void
    {
        self::assertTrue(
            $this->guard->paymentBelongsToSigningAccount(
                $this->account(1),
                ['custom_data' => ['account_sylius_id' => 1]],
            ),
        );
    }

    public function testItAcceptsAMatchingNumericStringId(): void
    {
        self::assertTrue(
            $this->guard->paymentBelongsToSigningAccount(
                $this->account(1),
                ['custom_data' => ['account_sylius_id' => '1']],
            ),
        );
    }

    public function testItRejectsWhenThePaymentBelongsToAnotherAccount(): void
    {
        self::assertFalse(
            $this->guard->paymentBelongsToSigningAccount(
                $this->account(1),
                ['custom_data' => ['account_sylius_id' => 2]],
            ),
        );
    }

    public function testItRejectsWhenCustomDataIsMissing(): void
    {
        self::assertFalse(
            $this->guard->paymentBelongsToSigningAccount($this->account(1), ['status' => 118]),
        );
    }

    public function testItRejectsWhenAccountSyliusIdIsMissing(): void
    {
        self::assertFalse(
            $this->guard->paymentBelongsToSigningAccount(
                $this->account(1),
                ['custom_data' => ['payment_id' => 42]],
            ),
        );
    }

    public function testItRejectsWhenThePaymentAccountHasNoId(): void
    {
        self::assertFalse(
            $this->guard->paymentBelongsToSigningAccount(
                $this->account(null),
                ['custom_data' => ['account_sylius_id' => 1]],
            ),
        );
    }

    private function account(?int $id): AccountInterface
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('getId')->willReturn($id);

        return $account;
    }
}
