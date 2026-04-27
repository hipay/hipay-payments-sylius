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

namespace HiPay\SyliusHiPayPlugin\Factory;

use HiPay\SyliusHiPayPlugin\Entity\SavedCardInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

interface SavedCardFactoryInterface extends FactoryInterface
{
    public function createNewFromPayment(PaymentInterface $payment): ?SavedCardInterface;
}
