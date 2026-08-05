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

use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\HostedPageConfigurationDefaultsInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\Positive;

class HostedPageSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        unset($options);

        $builder
            ->add('theme_code', TextType::class, [
                'label' => 'sylius_hipay_plugin.form.hosted_page.theme_code',
                'help' => 'sylius_hipay_plugin.form.hosted_page.theme_code_help',
                'required' => false,
            ])
            ->add('display_cancel_button', CheckboxType::class, [
                'label' => 'sylius_hipay_plugin.form.hosted_page.display_cancel_button',
                'required' => false,
            ])
            ->add('time_limit_to_pay', IntegerType::class, [
                'label' => 'sylius_hipay_plugin.form.hosted_page.time_limit_to_pay',
                'help' => 'sylius_hipay_plugin.form.hosted_page.time_limit_to_pay_help',
                'required' => false,
                'empty_data' => HostedPageConfigurationDefaultsInterface::TIME_LIMIT_TO_PAY,
                'constraints' => [
                    new LessThanOrEqual(value: 2140000, message: 'sylius_hipay_plugin.hosted_page.time_limit_to_pay.less_than_or_equal', groups: ['sylius']),
                    new Positive(message: 'sylius_hipay_plugin.hosted_page.time_limit_to_pay.positive', groups: ['sylius']),
                ],
            ])
        ;
    }
}
