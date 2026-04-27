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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Multibanco reference expiration limits (HiPay API values).
 *
 * @see https://developer.hipay.com/
 */
class MultibancoConfigurationType extends AbstractType
{
    /**
     * Label keys are translated; values are sent to the HiPay API.
     *
     * @var array<string, string>
     */
    private const MULTI_BANCO_EXPIRATION_LIMITS = [
        'sylius_hipay_plugin.form.multibanco.expiration.h1' => 'H1',
        'sylius_hipay_plugin.form.multibanco.expiration.h3' => 'H3',
        'sylius_hipay_plugin.form.multibanco.expiration.h6' => 'H6',
        'sylius_hipay_plugin.form.multibanco.expiration.h12' => 'H12',
        'sylius_hipay_plugin.form.multibanco.expiration.same_day' => '0',
        'sylius_hipay_plugin.form.multibanco.expiration.d1' => '1',
        'sylius_hipay_plugin.form.multibanco.expiration.d3' => '3',
        'sylius_hipay_plugin.form.multibanco.expiration.d30' => '30',
        'sylius_hipay_plugin.form.multibanco.expiration.d90' => '90',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        unset($options);

        $builder
            ->add('expiration_limit', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.multibanco.expiration_limit',
                'help' => 'sylius_hipay_plugin.form.multibanco.expiration_limit_help',
                'choices' => self::MULTI_BANCO_EXPIRATION_LIMITS,
                'required' => false,
            ])
        ;
    }

    public function getParent(): string
    {
        return GeneralConfigurationType::class;
    }
}
