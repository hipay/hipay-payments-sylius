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

namespace HiPay\SyliusHiPayPlugin\Fixture\Factory;

use HiPay\SyliusHiPayPlugin\Entity\Account;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AccountExampleFactory extends AbstractExampleFactory
{
    private OptionsResolver $optionsResolver;

    public function __construct()
    {
        $this->optionsResolver = new OptionsResolver();
        $this->configureOptions($this->optionsResolver);
    }

    public function create(array $options = []): AccountInterface
    {
        $options = $this->optionsResolver->resolve($options);

        $account = new Account();
        $account->setName($options['name']);
        $account->setCode($options['code']);
        $account->setApiUsername($options['api_username']);
        $account->setApiPassword($options['api_password']);
        $account->setSecretPassphrase($options['secret_passphrase']);
        $account->setTestApiUsername($options['test_api_username']);
        $account->setTestApiPassword($options['test_api_password']);
        $account->setTestSecretPassphrase($options['test_secret_passphrase']);
        $account->setPublicUsername($options['public_username']);
        $account->setPublicPassword($options['public_password']);
        $account->setTestPublicUsername($options['test_public_username']);
        $account->setTestPublicPassword($options['test_public_password']);
        $account->setEnvironment($options['environment']);
        $account->setDebugMode($options['debug_mode']);

        return $account;
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) — Options $options required by OptionsResolver lazy default detection */
    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('name', fn (Options $options): string => 'HiPay Account ' . substr(bin2hex(random_bytes(3)), 0, 6))
            ->setDefault('code', fn (Options $options): string => 'hipay-' . substr(bin2hex(random_bytes(4)), 0, 8))
            ->setDefault('api_username', fn (Options $options): string => 'api_' . bin2hex(random_bytes(8)))
            ->setDefault('api_password', fn (Options $options): string => bin2hex(random_bytes(16)))
            ->setDefault('secret_passphrase', fn (Options $options): string => bin2hex(random_bytes(16)))
            ->setDefault('test_api_username', fn (Options $options): string => 'test_api_' . bin2hex(random_bytes(8)))
            ->setDefault('test_api_password', fn (Options $options): string => bin2hex(random_bytes(16)))
            ->setDefault('test_secret_passphrase', fn (Options $options): string => bin2hex(random_bytes(16)))
            ->setDefault('public_username', fn (Options $options): string => 'public_' . bin2hex(random_bytes(8)))
            ->setDefault('public_password', fn (Options $options): string => bin2hex(random_bytes(16)))
            ->setDefault('test_public_username', fn (Options $options): string => 'test_public_' . bin2hex(random_bytes(8)))
            ->setDefault('test_public_password', fn (Options $options): string => bin2hex(random_bytes(16)))
            ->setDefault('environment', AccountInterface::ENVIRONMENT_TEST)
            ->setAllowedValues('environment', [AccountInterface::ENVIRONMENT_TEST, AccountInterface::ENVIRONMENT_PRODUCTION])
            ->setDefault('debug_mode', false)
            ->setAllowedTypes('debug_mode', 'bool')
        ;
    }
}
