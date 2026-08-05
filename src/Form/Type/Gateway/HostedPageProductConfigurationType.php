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

namespace HiPay\SyliusHiPayPlugin\Form\Type\Gateway;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HostedPageProductConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'label' => 'sylius_hipay_plugin.form.hosted_page.product.enabled',
                'help' => 'sylius_hipay_plugin.form.hosted_page.product.enabled_help',
                'required' => false,
            ])
            ->add('position', IntegerType::class, [
                'label' => 'sylius_hipay_plugin.form.hosted_page.product.position',
                'help' => 'sylius_hipay_plugin.form.hosted_page.product.position_help',
                'required' => false,
                'empty_data' => '0',
            ])
        ;

        /** @var class-string<\Symfony\Component\Form\FormTypeInterface> $innerType */
        $innerType = $options['inner_type'];
        $builder->add('configuration', $innerType, [
            'payment_product' => $options['payment_product'],
            'account_code' => $options['account_code'],
            'label' => false,
            'validation_groups' => static function (FormInterface $form): array {
                $parent = $form->getParent();
                $enabled = null !== $parent && $parent->has('enabled')
                    ? $parent->get('enabled')->getData()
                    : true;

                return true === $enabled ? ['sylius'] : [];
            },
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            /** @var array<string, mixed>|null $data */
            $data = $event->getData();
            if (!is_array($data)) {
                $data = [];
            }
            $data += ['enabled' => true, 'position' => 0];
            $event->setData($data);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            if (null === $data || [] === $data) {
                $event->setData(['enabled' => '1', 'position' => '0']);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['payment_product', 'inner_type', 'account_code'])
            ->setAllowedTypes('payment_product', 'string')
            ->setAllowedTypes('inner_type', 'string')
            ->setAllowedTypes('account_code', 'string')
        ;
    }
}
