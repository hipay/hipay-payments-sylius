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

namespace HiPay\SyliusHiPayPlugin\Payment;

use Sylius\Component\Core\Model\OrderInterface;

interface OrphanPaymentCancellerInterface
{
    /**
     * Cancel all but the most recent "new" HiPay payment on the given order.
     *
     * @return int Number of orphan payments cancelled
     */
    public function cancelOrphanPayments(OrderInterface $order): int;
}
