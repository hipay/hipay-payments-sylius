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

use function get_debug_type;
use HiPay\SyliusHiPayPlugin\Entity\OneyShippingMethod;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Oney\OneyStandardShippingMethod;
use InvalidArgumentException;
use function is_object;
use function is_string;
use function sprintf;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\Component\Shipping\Repository\ShippingMethodRepositoryInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use ValueError;

final class OneyShippingMethodExampleFactory extends AbstractExampleFactory
{
    private OptionsResolver $optionsResolver;

    /**
     * @param ShippingMethodRepositoryInterface<ShippingMethodInterface> $shippingMethodRepository
     */
    public function __construct(
        private ShippingMethodRepositoryInterface $shippingMethodRepository,
    ) {
        $this->optionsResolver = new OptionsResolver();
        $this->configureOptions($this->optionsResolver);
    }

    public function create(array $options = []): OneyShippingMethod
    {
        $options = $this->optionsResolver->resolve($options);

        $mapping = new OneyShippingMethod();
        $mapping->setShippingMethod($options['shipping_method']);
        $mapping->setOneyShippingMethod($options['oney_shipping_method']);
        $mapping->setOneyPreparationTime($options['oney_preparation_time']);
        $mapping->setOneyDeliveryTime($options['oney_delivery_time']);

        return $mapping;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['shipping_method', 'oney_shipping_method'])
            ->setDefaults([
                'oney_preparation_time' => 0,
                'oney_delivery_time' => 0,
            ])
            ->setAllowedTypes('oney_preparation_time', 'int')
            ->setAllowedTypes('oney_delivery_time', 'int')
            ->setNormalizer('shipping_method', LazyOption::getOneBy($this->shippingMethodRepository, 'code'))
            ->setNormalizer('oney_shipping_method', function (Options $options, mixed $value): OneyStandardShippingMethod {
                unset($options);

                return $this->resolveOneyShippingMethod($value);
            })
        ;
    }

    protected function resolveOneyShippingMethod(mixed $value): OneyStandardShippingMethod
    {
        if ($value instanceof OneyStandardShippingMethod) {
            return $value;
        }

        if (is_string($value)) {
            foreach (OneyStandardShippingMethod::cases() as $case) {
                if ($case->name === $value) {
                    return $case;
                }
            }

            try {
                return OneyStandardShippingMethod::from($value);
            } catch (ValueError) {
                // Fall through to detailed error below.
            }
        }

        throw new InvalidArgumentException(sprintf(
            'Invalid Oney standard shipping method value; expected enum case name, backed value string, or %s, got %s.',
            OneyStandardShippingMethod::class,
            is_object($value) ? $value::class : get_debug_type($value),
        ));
    }
}
