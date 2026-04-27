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

namespace HiPay\SyliusHiPayPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Unused parameter required by interface
        unset($options);

        $builder
            ->add('environment', ChoiceType::class, [
                'choices' => [
                    'sylius_hipay_plugin.ui.test_platform' => 'test',
                    'sylius_hipay_plugin.ui.live_platform' => 'live',
                ],
                'label' => 'sylius_hipay_plugin.ui.platform',
                'constraints' => [
                    new NotBlank(message: 'sylius_hipay_plugin.environment.not_blank', groups: ['sylius']),
                ],
            ])
            ->add('username', TextType::class, [
                'label' => 'sylius_hipay_plugin.ui.username',
                'constraints' => [
                    new NotBlank([
                        'message' => 'sylius_hipay_plugin.username.not_blank',
                        'groups' => ['sylius'],
                    ]),
                ],
            ])
            ->add('password', TextType::class, [
                'label' => 'sylius_hipay_plugin.ui.password',
                'constraints' => [
                    new NotBlank(message: 'sylius_hipay_plugin.password.not_blank', groups: ['sylius']),
                ],
            ])
            ->add('secret_passphrase', TextType::class, [
                'label' => 'sylius_hipay_plugin.ui.secret_passphrase',
                'help' => 'sylius_hipay_plugin.ui.secret_passphrase_help',
                'constraints' => [
                    new NotBlank(message: 'sylius_hipay_plugin.secret_passphrase.not_blank', groups: ['sylius']),
                ],
            ])
            ->add('api_version', TextType::class, [
                'label' => 'sylius_hipay_plugin.ui.api_version',
                'help' => 'sylius_hipay_plugin.ui.api_version_help',
                'required' => false,
                'data' => 'v1',
            ])
            ->add('capture_mode', ChoiceType::class, [
                'choices' => [
                    'sylius_hipay_plugin.ui.capture_mode_options.capture' => 'capture',
                    'sylius_hipay_plugin.ui.capture_mode_options.authorization' => 'authorization',
                ],
                'label' => 'sylius_hipay_plugin.ui.capture_mode',
                'help' => 'sylius_hipay_plugin.ui.capture_mode_help',
                'required' => false,
                'data' => 'capture',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // Unused parameter required by interface
        // No additional options needed
        unset($resolver);
    }
}
