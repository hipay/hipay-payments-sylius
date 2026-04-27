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
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Customer\Model\CustomerInterface;

class SavedCardRepository extends EntityRepository implements SavedCardRepositoryInterface
{
    /**
     * Returns only cards that are authorized and not expired for the given customer.
     * Uses string comparison on zero-padded year (4 chars) and month (2 chars).
     *
     * @return SavedCardInterface[]
     */
    public function findEligibleByCustomer(CustomerInterface $customer, DateTimeImmutable $now): array
    {
        $currentYear = $now->format('Y');
        $currentMonth = $now->format('m');

        // @phpstan-ignore-next-line
        return $this->createQueryBuilder('sc')
            ->andWhere('sc.customer = :customer')
            ->andWhere('sc.authorized = :authorized')
            ->andWhere(
                'sc.expiryYear > :currentYear OR ' .
                '(sc.expiryYear = :currentYear AND sc.expiryMonth >= :currentMonth)',
            )
            ->setParameter('customer', $customer)
            ->setParameter('authorized', true)
            ->setParameter('currentYear', $currentYear)
            ->setParameter('currentMonth', $currentMonth)
            ->getQuery()
            ->getResult();
    }
}
