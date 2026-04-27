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

namespace HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Default color/font values are aligned with the HiPay JS SDK defaults
 * (material theme — https://libs.hipay.com/themes/material.min.css).
 */
class OneyCreditLongConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        unset($options);

        $builder
            ->add('promotion_code', TextType::class, [
                'label' => 'sylius_hipay_plugin.form.oney_credit_long.promotion_code',
                'required' => true,
                'constraints' => [
                    new NotBlank(groups: ['sylius']),
                ],
            ])
        ;
    }

    public function getParent(): string
    {
        return GeneralConfigurationType::class;
    }
}
