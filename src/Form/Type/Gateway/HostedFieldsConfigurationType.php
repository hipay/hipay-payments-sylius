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

use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\GeneralConfigurationType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerRegistryInterface;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\PaymentProductProviderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

class HostedFieldsConfigurationType extends AbstractType
{
    public function __construct(
        private readonly AccountProviderInterface $accountProvider,
        private readonly PaymentProductProviderInterface $paymentProductProvider,
        private readonly PaymentProductHandlerRegistryInterface $paymentProductHandlerRegistry,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        unset($options);

        $builder = new DynamicFormBuilder($builder);

        $builder
            ->add('account', ChoiceType::class, [
                'choices' => $this->accountProvider->getForChoiceList(),
                'label' => 'sylius_hipay_plugin.ui.account',
                'placeholder' => 'sylius_hipay_plugin.ui.account_placeholder',
                'required' => true,
                'constraints' => [
                    new NotBlank(groups: ['sylius']),
                ],
            ])
            ->addDependent('payment_product', 'account', function (DependentField $field, ?string $account): void {
                if (null === $account || '' === $account) {
                    return;
                }

                $codes = $this->paymentProductProvider->getAvailableProductCodesByAccountCode($account);
                $field->add(ChoiceType::class, [
                    'label' => 'sylius_hipay_plugin.ui.payment_products',
                    'placeholder' => 'sylius_hipay_plugin.ui.payment_placeholder',
                    'choices' => $this->paymentProductProvider->getAllForChoiceList($account),
                    'choice_attr' => function (string $key) use ($codes) {
                        return !in_array($key, $codes) ? ['disabled' => 'disabled'] : [];
                    },
                    'required' => true,
                    'constraints' => [
                        new NotBlank(groups: ['sylius']),
                    ],
                ]);
            })
            ->addDependent('use_authorize', 'payment_product', function (DependentField $field): void {
                $field->add(CheckboxType::class, [
                    'label' => 'sylius_hipay_plugin.form.configuration.capture_mode',
                    'help' => 'sylius_hipay_plugin.form.configuration.capture_mode_help',
                ]);
            })
            ->addDependent('configuration', 'payment_product', function (DependentField $field, ?string $paymentProduct): void {
                if (null === $paymentProduct || '' === $paymentProduct) {
                    return;
                }

                $handler = $this->paymentProductHandlerRegistry->getForPaymentProduct($paymentProduct);
                $formType = $handler?->getFormType() ?? GeneralConfigurationType::class;

                $field->add($formType, ['payment_product' => $paymentProduct]);
            })
        ;
    }
}
