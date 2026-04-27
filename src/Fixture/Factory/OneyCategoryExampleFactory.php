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

namespace HiPay\SyliusHiPayPlugin\Fixture\Factory;

use function ctype_digit;
use function get_debug_type;
use HiPay\SyliusHiPayPlugin\Entity\OneyCategory;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Oney\OneyStandardCategory;
use InvalidArgumentException;
use function is_int;
use function is_object;
use function is_string;
use function sprintf;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class OneyCategoryExampleFactory extends AbstractExampleFactory
{
    private OptionsResolver $optionsResolver;

    /**
     * @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository
     */
    public function __construct(
        private TaxonRepositoryInterface $taxonRepository,
    ) {
        $this->optionsResolver = new OptionsResolver();
        $this->configureOptions($this->optionsResolver);
    }

    public function create(array $options = []): OneyCategory
    {
        $options = $this->optionsResolver->resolve($options);

        $oneyCategory = new OneyCategory();
        $oneyCategory->setTaxon($options['taxon']);
        $oneyCategory->setOneyCategory($options['oney_category']);

        return $oneyCategory;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['taxon', 'oney_category'])
            ->setNormalizer('taxon', LazyOption::getOneBy($this->taxonRepository, 'code'))
            ->setNormalizer('oney_category', function (Options $options, mixed $value): OneyStandardCategory {
                unset($options);

                return $this->resolveOneyCategory($value);
            })
        ;
    }

    protected function resolveOneyCategory(mixed $value): OneyStandardCategory
    {
        if ($value instanceof OneyStandardCategory) {
            return $value;
        }

        if (is_int($value)) {
            return OneyStandardCategory::from($value);
        }

        if (is_string($value)) {
            if ('' !== $value && ctype_digit($value)) {
                return OneyStandardCategory::from((int) $value);
            }

            foreach (OneyStandardCategory::cases() as $case) {
                if ($case->name === $value) {
                    return $case;
                }
            }
        }

        throw new InvalidArgumentException(sprintf(
            'Invalid Oney standard category value; expected int, numeric string, or enum case name, got %s.',
            is_object($value) ? $value::class : get_debug_type($value),
        ));
    }
}
