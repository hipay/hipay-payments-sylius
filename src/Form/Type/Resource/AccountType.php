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

namespace HiPay\SyliusHiPayPlugin\Form\Type\Resource;

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class AccountType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        unset($options);

        $builder
            ->add('name', TextType::class, [
                'label' => 'sylius_hipay_plugin.form.account.name',
            ])
            ->add('code', TextType::class, [
                'label' => 'sylius_hipay_plugin.form.account.code',
            ])
            ->add('environment', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.account.environment',
                'choices' => [
                    'sylius_hipay_plugin.ui.test' => AccountInterface::ENVIRONMENT_TEST,
                    'sylius_hipay_plugin.ui.production' => AccountInterface::ENVIRONMENT_PRODUCTION,
                ],
            ])
            ->add('apiUsername', TextType::class, [
                'label' => 'sylius_hipay_plugin.form.account.api_username',
                'help' => '',
            ])
            ->add('apiPassword', PasswordType::class, [
                'label' => 'sylius_hipay_plugin.form.account.api_password',
                'required' => false,
            ])
            ->add('secretPassphrase', PasswordType::class, [
                'label' => 'sylius_hipay_plugin.form.account.secret_passphrase',
                'required' => false,
            ])
            ->add('publicUsername', TextType::class, [
                'label' => 'sylius_hipay_plugin.form.account.public_username',
            ])
            ->add('publicPassword', PasswordType::class, [
                'label' => 'sylius_hipay_plugin.form.account.public_password',
                'required' => false,
            ])
            ->add('testApiUsername', TextType::class, [
                'label' => 'sylius_hipay_plugin.form.account.test_api_username',
            ])
            ->add('testApiPassword', PasswordType::class, [
                'label' => 'sylius_hipay_plugin.form.account.test_api_password',
                'required' => false,
            ])
            ->add('testSecretPassphrase', PasswordType::class, [
                'label' => 'sylius_hipay_plugin.form.account.test_secret_passphrase',
                'required' => false,
            ])
            ->add('testPublicUsername', TextType::class, [
                'label' => 'sylius_hipay_plugin.form.account.test_public_username',
            ])
            ->add('testPublicPassword', PasswordType::class, [
                'label' => 'sylius_hipay_plugin.form.account.test_public_password',
                'required' => false,
            ])
            ->add('debugMode', CheckboxType::class, [
                'label' => 'sylius_hipay_plugin.form.account.debug_mode',
                'required' => false,
                'help' => 'sylius_hipay_plugin.form.account.debug_mode_help',
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $account = $event->getData();
            $form = $event->getForm();

            if ($account instanceof AccountInterface && null !== $account->getId()) {
                $form->add('code', TextType::class, [
                    'label' => 'sylius_hipay_plugin.form.account.code',
                    'disabled' => true,
                ]);
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'managePassword']);
    }

    public function managePassword(FormEvent $event): void
    {
        $data = $event->getData();
        $account = $event->getForm()->getData();
        if (!is_array($data) || !$account instanceof AccountInterface) {
            return;
        }

        $productionPasswords = ['apiPassword', 'secretPassphrase', 'publicPassword'];
        $testPasswords = ['testApiPassword', 'testSecretPassphrase', 'testPublicPassword'];

        $newEnvironment = is_string($data['environment'] ?? null) ? $data['environment'] : $account->getEnvironment();

        $fieldsToWipe = [];
        if ($newEnvironment !== $account->getEnvironment()) {
            $fieldsToWipe = AccountInterface::ENVIRONMENT_PRODUCTION === $newEnvironment
                ? $testPasswords
                : $productionPasswords;
        }

        $stored = [
            'apiPassword' => $account->getApiPassword(),
            'secretPassphrase' => $account->getSecretPassphrase(),
            'publicPassword' => $account->getPublicPassword(),
            'testApiPassword' => $account->getTestApiPassword(),
            'testSecretPassphrase' => $account->getTestSecretPassphrase(),
            'testPublicPassword' => $account->getTestPublicPassword(),
        ];

        foreach ($stored as $field => $value) {
            if (in_array($field, $fieldsToWipe, true)) {
                $data[$field] = '';

                continue;
            }

            $submitted = $data[$field] ?? null;
            if ('' !== $value && ('' === $submitted || null === $submitted)) {
                $data[$field] = $value;
            }
        }

        $event->setData($data);
    }
}
