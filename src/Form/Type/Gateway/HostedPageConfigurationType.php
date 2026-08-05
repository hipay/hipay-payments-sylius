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
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\HostedPageProductsProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerRegistryInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PluginPaymentProduct;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\PaymentProductProviderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;
use Throwable;

class HostedPageConfigurationType extends AbstractType
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
            ->addDependent('hosted_page', 'account', function (DependentField $field, ?string $account): void {
                if (null === $account || '' === $account) {
                    return;
                }

                $field->add(HostedPageSettingsType::class);
            })
            ->addDependent('use_authorize', 'account', function (DependentField $field, ?string $account): void {
                if (null === $account || '' === $account) {
                    return;
                }

                $field->add(CaptureModeChoiceType::class);
            })
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, $this->syncProductConfigurations(...));
        $builder->addEventListener(FormEvents::PRE_SUBMIT, $this->syncProductConfigurations(...));
    }

    private function syncProductConfigurations(FormEvent $event): void
    {
        $data = $event->getData();
        $products = [];
        $accountCode = null;

        if (is_array($data)) {
            $account = $data['account'] ?? null;
            if (is_string($account) && '' !== $account) {
                $accountCode = $account;

                try {
                    $available = $this->paymentProductProvider->getAvailableProductCodesByAccountCode($account);
                    $products = array_values(array_filter(
                        $available,
                        static fn (string $code): bool => null !== PluginPaymentProduct::tryFrom($code) &&
                            !in_array($code, HostedPageProductsProcessorPayment::EXCLUDED_PRODUCTS, true),
                    ));
                } catch (Throwable) {
                    // API not available, keep empty
                }
            }
        }

        $this->buildProductConfigurationForm($event->getForm(), $products, $accountCode);
    }

    /**
     * @param string[] $products
     */
    private function buildProductConfigurationForm(FormInterface $form, array $products, ?string $accountCode): void
    {
        if (!$form->has('product_configurations')) {
            $form->add('product_configurations', FormType::class, [
                'label' => false,
                'compound' => true,
            ]);
        }

        $configForm = $form->get('product_configurations');

        foreach ($products as $productCode) {
            if ($configForm->has($productCode)) {
                if ($accountCode === $configForm->get($productCode)->getConfig()->getOption('account_code')) {
                    continue;
                }

                $configForm->remove($productCode);
            }

            $handler = $this->paymentProductHandlerRegistry->getForPaymentProduct($productCode);
            $innerType = $handler?->getFormType() ?? GeneralConfigurationType::class;

            $configForm->add($productCode, HostedPageProductConfigurationType::class, [
                'payment_product' => $productCode,
                'inner_type' => $innerType,
                'account_code' => $accountCode,
                'label' => false,
            ]);
        }

        foreach ($configForm as $child) {
            if (!in_array($child->getName(), $products, true)) {
                $configForm->remove($child->getName());
            }
        }
    }
}
