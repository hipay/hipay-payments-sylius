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

namespace HiPay\SyliusHiPayPlugin\Provider;

use HiPay\SyliusHiPayPlugin\Entity\OneyShippingMethodInterface;
use Sylius\Component\Core\Model\OrderInterface;

interface OneyShippingMethodProviderInterface
{
    public function getByOrder(OrderInterface $order): ?OneyShippingMethodInterface;
}
