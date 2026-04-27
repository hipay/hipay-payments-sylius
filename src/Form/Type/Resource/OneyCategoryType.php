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

namespace HiPay\SyliusHiPayPlugin\Form\Type\Resource;

use HiPay\SyliusHiPayPlugin\Entity\OneyCategoryInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Oney\OneyStandardCategory;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Bundle\TaxonomyBundle\Doctrine\ORM\TaxonRepository;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;

final class OneyCategoryType extends AbstractResourceType
{
    /**
     * @param TaxonRepository<TaxonInterface> $taxonRepository
     */
    public function __construct(
        string $dataClass,
        array $validationGroups,
        private readonly EntityRepository $taxonRepository,
        private readonly EntityRepository $oneyCategoryRepository,
    ) {
        parent::__construct($dataClass, $validationGroups);
    }

    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        unset($options);

        /** @var OneyCategoryInterface|null $data */
        $data = $builder->getData();

        // @phpstan-ignore-next-line
        $excludedIds = $this->oneyCategoryRepository->getTaxonIdsToExclud($data);

        $builder
            ->add('taxon', EntityType::class, [
                'class' => $this->taxonRepository->getClassName(),
                'choice_label' => static fn (TaxonInterface $taxon): string => (string) $taxon->getName(),
                'label' => 'sylius_hipay_plugin.form.oney_category.sylius_category',
                'placeholder' => 'sylius_hipay_plugin.form.oney_category.sylius_category_placeholder',
                'required' => true,
                'query_builder' => function () use ($excludedIds) {
                    $qb = $this->taxonRepository->createQueryBuilder('t');

                    $qb->andWhere($qb->expr()->eq('t.level', ':level'))
                        ->setParameter('level', 1);
                    if ($excludedIds !== []) {
                        $qb
                            ->andWhere($qb->expr()->notIn('t.id', ':excluded'))
                            ->setParameter('excluded', $excludedIds);
                    }

                    return $qb->orderBy('t.position');
                },
            ])
            ->add('oneyCategory', EnumType::class, [
                'class' => OneyStandardCategory::class,
                'label' => 'sylius_hipay_plugin.form.oney_category.oney_category',
                'placeholder' => 'sylius_hipay_plugin.form.oney_category.oney_category_placeholder',
                'required' => true,
                'choice_label' => [OneyStandardCategory::class, 'choiceTranslationKey'],
            ])
        ;
    }
}
