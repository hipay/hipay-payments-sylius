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

use HiPay\SyliusHiPayPlugin\Entity\OneyCategoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ProductInterface;

final class OneyCategoryProvider implements OneyCategoryProviderInterface
{
    public function __construct(
        private readonly EntityRepository $oneyCategoryRepository,
    ) {
    }

    public function getByProduct(ProductInterface $product): ?OneyCategoryInterface
    {
        $currentTaxon = $product->getMainTaxon();
        if (null === $currentTaxon || 0 === $currentTaxon->getLevel()) {
            return null;
        }
        while ($currentTaxon->getParent() !== null && $currentTaxon->getLevel() > 1) {
            $currentTaxon = $currentTaxon->getParent();
        }

        if (1 !== $currentTaxon->getLevel()) {
            return null;
        }

        /** @var OneyCategoryInterface|null $mapping */
        $mapping = $this->oneyCategoryRepository->findOneBy(['taxon' => $currentTaxon]);

        return $mapping;
    }
}
