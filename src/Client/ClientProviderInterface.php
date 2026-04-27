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

namespace HiPay\SyliusHiPayPlugin\Client;

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

interface ClientProviderInterface
{
    public function getForPaymentMethod(PaymentMethodInterface $paymentMethod): HiPayClientInterface;

    public function getForAccount(AccountInterface $account): HiPayClientInterface;

    public function getForAccountCode(string $accountCode): HiPayClientInterface;
}
