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

use DateTimeImmutable;
use HiPay\SyliusHiPayPlugin\Entity\SavedCardInterface;
use Sylius\Component\Customer\Model\CustomerInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

interface SavedCardRepositoryInterface extends RepositoryInterface
{
    /**
     * @return SavedCardInterface[]
     */
    public function findEligibleByCustomer(CustomerInterface $customer, DateTimeImmutable $now): array;
}
