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

use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\PaypalConfigurationDefaultsInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Range;

/**
 * PayPal V2 button style and behaviour options.
 *
 * @see https://developer.hipay.com/online-payments/payment-means/paypal
 */
class PaypalConfigurationType extends AbstractType
{
    private const BUTTON_SHAPES = [
        'sylius_hipay_plugin.form.paypal.button_shape_option.pill' => PaypalConfigurationDefaultsInterface::BUTTON_SHAPE,
        'sylius_hipay_plugin.form.paypal.button_shape_option.rect' => 'rect',
    ];

    private const BUTTON_COLORS = [
        'sylius_hipay_plugin.form.paypal.button_color_option.gold' => PaypalConfigurationDefaultsInterface::BUTTON_COLOR,
        'sylius_hipay_plugin.form.paypal.button_color_option.blue' => 'blue',
        'sylius_hipay_plugin.form.paypal.button_color_option.black' => 'black',
        'sylius_hipay_plugin.form.paypal.button_color_option.silver' => 'silver',
        'sylius_hipay_plugin.form.paypal.button_color_option.white' => 'white',
    ];

    private const BUTTON_LABELS = [
        'sylius_hipay_plugin.form.paypal.button_label_option.pay' => PaypalConfigurationDefaultsInterface::BUTTON_LABEL,
        'sylius_hipay_plugin.form.paypal.button_label_option.paypal' => 'paypal',
        'sylius_hipay_plugin.form.paypal.button_label_option.subscribe' => 'subscribe',
        'sylius_hipay_plugin.form.paypal.button_label_option.checkout' => 'checkout',
        'sylius_hipay_plugin.form.paypal.button_label_option.buynow' => 'buynow',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        unset($options);

        $builder
            ->add('can_pay_later', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.paypal.can_pay_later',
                'choices' => [
                    'sylius.ui.yes_label' => true,
                    'sylius.ui.no_label' => false,
                ],
                'empty_data' => PaypalConfigurationDefaultsInterface::CAN_PAY_LATER,
            ])
            ->add('button_shape', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.paypal.button_shape',
                'choices' => self::BUTTON_SHAPES,
                'empty_data' => PaypalConfigurationDefaultsInterface::BUTTON_SHAPE,
            ])
            ->add('button_color', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.paypal.button_color',
                'choices' => self::BUTTON_COLORS,
                'empty_data' => PaypalConfigurationDefaultsInterface::BUTTON_COLOR,
            ])
            ->add('button_label', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.paypal.button_label',
                'choices' => self::BUTTON_LABELS,
                'empty_data' => PaypalConfigurationDefaultsInterface::BUTTON_LABEL,
            ])
            ->add('button_height', IntegerType::class, [
                'label' => 'sylius_hipay_plugin.form.paypal.button_height',
                'empty_data' => (string) PaypalConfigurationDefaultsInterface::BUTTON_HEIGHT,
                'required' => true,
                'constraints' => [
                    new Range(min: 25, max: 55, groups: ['sylius']),
                ],
            ])
        ;
    }

    public function getParent(): string
    {
        return GeneralConfigurationType::class;
    }
}
