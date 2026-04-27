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

namespace HiPay\SyliusHiPayPlugin\Mailer;

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use Sylius\Component\Core\Model\PaymentInterface;

interface MultibancoReferenceEmailManagerInterface
{
    public function sendMultibancoReferenceEmail(AccountInterface $account, PaymentInterface $payment, array $referenceToPay): void;
}
