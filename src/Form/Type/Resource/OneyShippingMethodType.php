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

use HiPay\SyliusHiPayPlugin\Entity\OneyShippingMethodInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Oney\OneyStandardShippingMethod;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Sylius\Bundle\ShippingBundle\Doctrine\ORM\ShippingMethodRepository;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;

final class OneyShippingMethodType extends AbstractResourceType
{
    /**
     * @param ShippingMethodRepository<ShippingMethodInterface> $shippingMethodRepository
     */
    public function __construct(
        string $dataClass,
        array $validationGroups,
        private readonly ShippingMethodRepository $shippingMethodRepository,
        private readonly EntityRepository $oneyShippingMethodRepository,
    ) {
        parent::__construct($dataClass, $validationGroups);
    }

    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        unset($options);

        /** @var OneyShippingMethodInterface|null $data */
        $data = $builder->getData();

        // @phpstan-ignore-next-line
        $excludedIds = $this->oneyShippingMethodRepository->getShippingMethodIdsToExclud($data);

        $builder
            ->add('shippingMethod', EntityType::class, [
                'class' => $this->shippingMethodRepository->getClassName(),
                'choice_label' => static fn (ShippingMethodInterface $shippingMethod): string => (string) $shippingMethod->getName(),
                'label' => 'sylius_hipay_plugin.form.oney_shipping_method.sylius_shipping_method',
                'placeholder' => 'sylius_hipay_plugin.form.oney_shipping_method.sylius_shipping_method_placeholder',
                'required' => true,
                'query_builder' => function () use ($excludedIds) {
                    $qb = $this->shippingMethodRepository->createQueryBuilder('sm');
                    if ($excludedIds !== []) {
                        $qb
                            ->andWhere($qb->expr()->notIn('sm.id', ':excluded'))
                            ->setParameter('excluded', $excludedIds);
                    }

                    return $qb->orderBy('sm.position');
                },
            ])
            ->add('oneyShippingMethod', EnumType::class, [
                'class' => OneyStandardShippingMethod::class,
                'label' => 'sylius_hipay_plugin.form.oney_shipping_method.oney_shipping_method',
                'placeholder' => 'sylius_hipay_plugin.form.oney_shipping_method.oney_shipping_method_placeholder',
                'required' => true,
                'choice_label' => [OneyStandardShippingMethod::class, 'choiceTranslationKey'],
            ])
            ->add('oneyPreparationTime', IntegerType::class, [
                'label' => 'sylius_hipay_plugin.form.oney_shipping_method.oney_preparation_time',
                'required' => true,
                'empty_data' => 0,
            ])
            ->add('oneyDeliveryTime', IntegerType::class, [
                'label' => 'sylius_hipay_plugin.form.oney_shipping_method.oney_delivery_time',
                'required' => true,
                'empty_data' => 0,
            ])
        ;
    }
}
