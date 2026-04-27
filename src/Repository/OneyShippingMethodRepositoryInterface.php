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

namespace HiPay\SyliusHiPayPlugin\Repository;

use HiPay\SyliusHiPayPlugin\Entity\OneyShippingMethodInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

interface OneyShippingMethodRepositoryInterface extends RepositoryInterface
{
    /**
     * @return array<int, int|string>
     */
    public function getShippingMethodIdsToExclud(?OneyShippingMethodInterface $oneyShippingMethod): array;
}
