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

use HiPay\SyliusHiPayPlugin\Entity\OneyCategoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class OneyCategoryRepository extends EntityRepository implements OneyCategoryRepositoryInterface
{
    /**
     * @return array<int, int|string>
     */
    public function getTaxonIdsToExclud(?OneyCategoryInterface $oneyCategory): array
    {
        $queryBuilder = $this->createQueryBuilder('oc')
            ->select('t.id')
            ->innerJoin('oc.taxon', 't');
        if (null !== $oneyCategory?->getTaxon()) {
            $queryBuilder->andWhere($queryBuilder->expr()->neq('t', ':taxon'))
                ->setParameter('taxon', $oneyCategory->getTaxon());
        }

        /** @var array<int, int|string> $ids */
        $ids = $queryBuilder
            ->getQuery()
            ->getSingleColumnResult();

        return $ids;
    }
}
