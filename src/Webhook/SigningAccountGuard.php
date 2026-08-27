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

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;

final class SigningAccountGuard implements SigningAccountGuardInterface
{
    public function paymentBelongsToSigningAccount(AccountInterface $paymentAccount, array $notification): bool
    {
        $customData = $notification['custom_data'] ?? null;
        if (!is_array($customData)) {
            return false;
        }

        $signingAccountId = $customData['account_sylius_id'] ?? null;
        if (!is_scalar($signingAccountId)) {
            return false;
        }

        $paymentAccountId = $paymentAccount->getId();
        if (null === $paymentAccountId) {
            return false;
        }

        return (int) $paymentAccountId === (int) $signingAccountId;
    }
}
