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

use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\ApplePayConfigurationDefaultsInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Apple Pay button style and display options.
 *
 * @see https://developer.hipay.com/online-payments/payment-means/apple-pay-web
 */
class ApplePayConfigurationType extends AbstractType
{
    private const SUPPORTED_NETWORKS = [
        'sylius_hipay_plugin.form.apple_pay.supported_network.visa' => 'visa',
        'sylius_hipay_plugin.form.apple_pay.supported_network.masterCard' => 'masterCard',
        'sylius_hipay_plugin.form.apple_pay.supported_network.cartesBancaires' => 'cartesBancaires',
        'sylius_hipay_plugin.form.apple_pay.supported_network.maestro' => 'maestro',
    ];

    private const BUTTON_TYPES = [
        'sylius_hipay_plugin.form.apple_pay.button_type_option.add-money' => 'add-money',
        'sylius_hipay_plugin.form.apple_pay.button_type_option.book' => 'book',
        'sylius_hipay_plugin.form.apple_pay.button_type_option.buy' => ApplePayConfigurationDefaultsInterface::BUTTON_TYPE,
        'sylius_hipay_plugin.form.apple_pay.button_type_option.check-out' => 'check-out',
        'sylius_hipay_plugin.form.apple_pay.button_type_option.continue' => 'continue',
        'sylius_hipay_plugin.form.apple_pay.button_type_option.contribute' => 'contribute',
        'sylius_hipay_plugin.form.apple_pay.button_type_option.donate' => 'donate',
        'sylius_hipay_plugin.form.apple_pay.button_type_option.order' => 'order',
        'sylius_hipay_plugin.form.apple_pay.button_type_option.pay' => 'pay',
        'sylius_hipay_plugin.form.apple_pay.button_type_option.plain' => 'plain',
        'sylius_hipay_plugin.form.apple_pay.button_type_option.reload' => 'reload',
        'sylius_hipay_plugin.form.apple_pay.button_type_option.rent' => 'rent',
        'sylius_hipay_plugin.form.apple_pay.button_type_option.set-up' => 'set-up',
        'sylius_hipay_plugin.form.apple_pay.button_type_option.subscribe' => 'subscribe',
        'sylius_hipay_plugin.form.apple_pay.button_type_option.support' => 'support',
        'sylius_hipay_plugin.form.apple_pay.button_type_option.tip' => 'tip',
        'sylius_hipay_plugin.form.apple_pay.button_type_option.top-up' => 'top-up',
    ];

    private const BUTTON_COLORS = [
        'sylius_hipay_plugin.form.apple_pay.button_color_option.black' => ApplePayConfigurationDefaultsInterface::BUTTON_COLOR,
        'sylius_hipay_plugin.form.apple_pay.button_color_option.white' => 'white',
        'sylius_hipay_plugin.form.apple_pay.button_color_option.white-outline' => 'white-outline',
    ];

    private const DISPLAY_MODES = [
        'sylius_hipay_plugin.form.apple_pay.display_mode_option.pop_up' => ApplePayConfigurationDefaultsInterface::DISPLAY_MODE,
        'sylius_hipay_plugin.form.apple_pay.display_mode_option.modal' => 'modal',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        unset($options);

        $builder
            ->add('display_name', TextType::class, [
                'label' => 'sylius_hipay_plugin.form.apple_pay.display_name',
                'help' => 'sylius_hipay_plugin.form.apple_pay.display_name_help',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('supported_networks', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.apple_pay.supported_networks',
                'choices' => self::SUPPORTED_NETWORKS,
                'multiple' => true,
                'expanded' => true,
                'empty_data' => ApplePayConfigurationDefaultsInterface::SUPPORTED_NETWORKS,
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'sylius_hipay_plugin.card.supported_networks.not_blank', groups: ['sylius']),
                ],
            ])
            ->add('button_type', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.apple_pay.button_type',
                'choices' => self::BUTTON_TYPES,
                'empty_data' => ApplePayConfigurationDefaultsInterface::BUTTON_TYPE,
            ])
            ->add('button_color', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.apple_pay.button_color',
                'choices' => self::BUTTON_COLORS,
                'empty_data' => ApplePayConfigurationDefaultsInterface::BUTTON_COLOR,
            ])
            ->add('multi_browser', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.apple_pay.multi_browser',
                'choices' => [
                    'sylius.ui.no_label' => false,
                    'sylius.ui.yes_label' => true,
                ],
            ])
            ->add('display_mode', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.apple_pay.display_mode',
                'choices' => self::DISPLAY_MODES,
                'empty_data' => ApplePayConfigurationDefaultsInterface::DISPLAY_MODE,
            ])
            ->add('only_active_card', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.apple_pay.only_active_card',
                'choices' => [
                    'sylius.ui.no_label' => false,
                    'sylius.ui.yes_label' => true,
                ],
                'choice_attr' => static function (mixed $choice, mixed $key = null, mixed $value = null): array {
                    unset($key, $value);

                    return true === $choice ? ['data-requires-merchant-identifier' => '1'] : [];
                },
            ])
            ->add('merchant_identifier', TextType::class, [
                'label' => 'sylius_hipay_plugin.form.apple_pay.merchant_identifier',
                'required' => false,
                'label_attr' => [
                    'class' => 'required',
                ],
                'empty_data' => '',
            ])
        ;

        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            if (true !== ($data['only_active_card'] ?? ApplePayConfigurationDefaultsInterface::ONLY_ACTIVE_CARD)) {
                $data['merchant_identifier'] = '';
                $event->setData($data);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->addNormalizer('constraints', static function (Options $options, mixed $constraints): array {
            unset($options);
            $list = is_array($constraints) ? $constraints : [];
            $list[] = new Callback(callback: static function (mixed $data, ExecutionContextInterface $context): void {
                if (!is_array($data)) {
                    return;
                }

                if (true !== ($data['only_active_card'] ?? ApplePayConfigurationDefaultsInterface::ONLY_ACTIVE_CARD)) {
                    return;
                }

                $merchant = $data['merchant_identifier'] ?? '';
                $trimmed = is_string($merchant) ? trim($merchant) : '';

                if ('' !== $trimmed) {
                    return;
                }

                $context->buildViolation('sylius_hipay_plugin.apple_pay.merchant_identifier.required_when_only_active_card')
                    ->atPath('[merchant_identifier]')
                    ->addViolation();
            }, groups: ['sylius']);

            return $list;
        });
    }

    public function getParent(): string
    {
        return GeneralConfigurationType::class;
    }
}
