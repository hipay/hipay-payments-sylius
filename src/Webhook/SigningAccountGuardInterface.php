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

interface SigningAccountGuardInterface
{
    public function paymentBelongsToSigningAccount(AccountInterface $paymentAccount, array $notification): bool;
}
