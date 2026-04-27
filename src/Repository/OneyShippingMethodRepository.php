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
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class OneyShippingMethodRepository extends EntityRepository implements OneyShippingMethodRepositoryInterface
{
    /**
     * @return array<int, int|string>
     */
    public function getShippingMethodIdsToExclud(?OneyShippingMethodInterface $oneyShippingMethod): array
    {
        $queryBuilder = $this->createQueryBuilder('osm')
            ->select('sm.id')
            ->innerJoin('osm.shippingMethod', 'sm');
        if (null !== $oneyShippingMethod?->getShippingMethod()) {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->neq('sm', ':shippingMethod'))
                ->setParameter('shippingMethod', $oneyShippingMethod->getShippingMethod());
        }

        /** @var array<int, int|string> $ids */
        $ids = $queryBuilder
            ->getQuery()
            ->getSingleColumnResult();

        return $ids;
    }
}
