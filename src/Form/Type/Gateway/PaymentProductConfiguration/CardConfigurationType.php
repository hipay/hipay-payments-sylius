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

use HiPay\SyliusHiPayPlugin\PaymentProduct\CardPaymentProduct;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\CardConfigurationDefaultsInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\ThreeDS\ThreeDSMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Default color/font values are aligned with the HiPay JS SDK defaults
 * (material theme — https://libs.hipay.com/themes/material.min.css).
 */
class CardConfigurationType extends AbstractType
{
    private const CARD_BRANDS = [
        'sylius_hipay_plugin.form.card.brand.cb' => 'cb',
        'sylius_hipay_plugin.form.card.brand.visa' => 'visa',
        'sylius_hipay_plugin.form.card.brand.mastercard' => 'mastercard',
        'sylius_hipay_plugin.form.card.brand.maestro' => 'maestro',
        'sylius_hipay_plugin.form.card.brand.bancontact' => 'bcmc',
        'sylius_hipay_plugin.form.card.brand.american_express' => 'american-express',
    ];

    private const FONT_SIZES = [
        '8px' => '8px',
        '10px' => '10px',
        '12px' => '12px',
        '14px' => '14px',
        '16px' => '16px',
        '18px' => '18px',
        '20px' => '20px',
    ];

    private const FONT_STYLES = [
        'sylius_hipay_plugin.form.card.font_style_option.normal' => 'normal',
        'sylius_hipay_plugin.form.card.font_style_option.italic' => 'italic',
        'sylius_hipay_plugin.form.card.font_style_option.oblique' => 'oblique',
    ];

    private const FONT_WEIGHTS = [
        'normal' => 'normal',
        'bold' => 'bold',
        '100' => '100',
        '200' => '200',
        '300' => '300',
        '400' => '400',
        '500' => '500',
        '600' => '600',
        '700' => '700',
        '800' => '800',
        '900' => '900',
        '1000' => '1000',
    ];

    private const TEXT_DECORATIONS = [
        'sylius_hipay_plugin.form.card.text_decoration_option.none' => 'none',
        'sylius_hipay_plugin.form.card.text_decoration_option.underline' => 'underline',
        'sylius_hipay_plugin.form.card.text_decoration_option.line_through' => 'line-through',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        unset($options);

        $builder
            ->add('allowed_brands', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.card.allowed_brands',
                'choices' => self::CARD_BRANDS,
                'empty_data' => CardPaymentProduct::getPaymentProducts(),
                'multiple' => true,
                'expanded' => true,
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'sylius_hipay_plugin.card.supported_networks.not_blank', groups: ['sylius']),
                ],
            ])
            ->add('one_click_enabled', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.card.one_click_enabled',
                'choices' => [
                    'sylius.ui.yes_label' => true,
                    'sylius.ui.no_label' => false,
                ],
                'empty_data' => true,
            ])
            ->add('three_ds_mode', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.card.three_ds_mode',
                'help' => 'sylius_hipay_plugin.form.card.three_ds_mode_help',
                'choices' => ThreeDSMode::getChoices(),
                'empty_data' => ThreeDSMode::IF_AVAILABLE,
            ])
            ->add('text_color', ColorType::class, [
                'label' => 'sylius_hipay_plugin.form.card.text_color',
                'empty_data' => CardConfigurationDefaultsInterface::TEXT_COLOR,
                'required' => false,
            ])
            ->add('placeholder_color', ColorType::class, [
                'label' => 'sylius_hipay_plugin.form.card.placeholder_color',
                'empty_data' => CardConfigurationDefaultsInterface::PLACEHOLDER_COLOR,
                'required' => false,
            ])
            ->add('icon_color', ColorType::class, [
                'label' => 'sylius_hipay_plugin.form.card.icon_color',
                'empty_data' => CardConfigurationDefaultsInterface::ICON_COLOR,
                'required' => false,
            ])
            ->add('invalid_text_color', ColorType::class, [
                'label' => 'sylius_hipay_plugin.form.card.invalid_text_color',
                'empty_data' => CardConfigurationDefaultsInterface::INVALID_TEXT_COLOR,
                'required' => false,
            ])
            ->add('valid_text_color', ColorType::class, [
                'label' => 'sylius_hipay_plugin.form.card.valid_text_color',
                'empty_data' => CardConfigurationDefaultsInterface::VALID_TEXT_COLOR,
                'required' => false,
            ])
            ->add('oneclick_highlight_color', ColorType::class, [
                'label' => 'sylius_hipay_plugin.form.card.oneclick_highlight_color',
                'empty_data' => CardConfigurationDefaultsInterface::ONECLICK_HIGHLIGHT_COLOR,
                'required' => false,
            ])
            ->add('save_button_color', ColorType::class, [
                'label' => 'sylius_hipay_plugin.form.card.save_button_color',
                'empty_data' => CardConfigurationDefaultsInterface::SAVE_BUTTON_COLOR,
                'required' => false,
            ])
            ->add('font_family', TextType::class, [
                'label' => 'sylius_hipay_plugin.form.card.font_family',
                'empty_data' => CardConfigurationDefaultsInterface::FONT_FAMILY,
                'required' => true,
                'constraints' => [
                    new NotBlank(groups: ['sylius']),
                ],
            ])
            ->add('font_size', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.card.font_size',
                'choices' => self::FONT_SIZES,
                'empty_data' => CardConfigurationDefaultsInterface::FONT_SIZE,
                'required' => true,
                'constraints' => [
                    new NotBlank(groups: ['sylius']),
                ],
            ])
            ->add('font_style', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.card.font_style',
                'choices' => self::FONT_STYLES,
                'empty_data' => CardConfigurationDefaultsInterface::FONT_STYLE,
                'required' => true,
                'constraints' => [
                    new NotBlank(groups: ['sylius']),
                ],
            ])
            ->add('font_weight', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.card.font_weight',
                'choices' => self::FONT_WEIGHTS,
                'empty_data' => CardConfigurationDefaultsInterface::FONT_WEIGHT,
                'required' => true,
                'constraints' => [
                    new NotBlank(groups: ['sylius']),
                ],
            ])
            ->add('text_decoration', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.card.text_decoration',
                'choices' => self::TEXT_DECORATIONS,
                'empty_data' => CardConfigurationDefaultsInterface::TEXT_DECORATION,
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
