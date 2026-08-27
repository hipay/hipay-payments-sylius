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

namespace Tests\HiPay\SyliusHiPayPlugin\Integration\Form\Type;

use HiPay\SyliusHiPayPlugin\Entity\Account;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Form\Type\Resource\AccountType;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AccountTypeTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->formFactory = self::getContainer()->get('form.factory');
    }

    public function testFormHasAllExpectedFields(): void
    {
        $form = $this->formFactory->create(AccountType::class, new Account());

        $this->assertTrue($form->has('name'));
        $this->assertTrue($form->has('code'));
        $this->assertTrue($form->has('environment'));
        $this->assertTrue($form->has('apiUsername'));
        $this->assertTrue($form->has('apiPassword'));
        $this->assertTrue($form->has('secretPassphrase'));
        $this->assertTrue($form->has('testApiUsername'));
        $this->assertTrue($form->has('testApiPassword'));
        $this->assertTrue($form->has('testSecretPassphrase'));
        $this->assertTrue($form->has('debugMode'));
    }

    public function testSubmitValidData(): void
    {
        $form = $this->formFactory->create(AccountType::class, new Account());

        $form->submit([
            'name' => 'My Account',
            'code' => 'my-account',
            'environment' => AccountInterface::ENVIRONMENT_TEST,
            'apiUsername' => 'prod_user',
            'apiPassword' => 'prod_pass',
            'secretPassphrase' => 'prod_secret',
            'testApiUsername' => 'test_user',
            'testApiPassword' => 'test_pass',
            'testSecretPassphrase' => 'test_secret',
            'debugMode' => true,
        ]);

        $this->assertTrue($form->isSynchronized());

        /** @var Account $account */
        $account = $form->getData();
        $this->assertSame('My Account', $account->getName());
        $this->assertSame('my-account', $account->getCode());
        $this->assertSame(AccountInterface::ENVIRONMENT_TEST, $account->getEnvironment());
        $this->assertSame('prod_user', $account->getApiUsername());
        $this->assertSame('prod_pass', $account->getApiPassword());
        $this->assertSame('prod_secret', $account->getSecretPassphrase());
        $this->assertSame('test_user', $account->getTestApiUsername());
        $this->assertSame('test_pass', $account->getTestApiPassword());
        $this->assertSame('test_secret', $account->getTestSecretPassphrase());
        $this->assertTrue($account->isDebugMode());
    }

    public function testCodeIsDisabledOnExistingAccount(): void
    {
        $account = new Account();
        $account->setName('Existing');
        $account->setCode('existing-code');

        $ref = new ReflectionProperty(Account::class, 'id');
        $ref->setValue($account, 42);

        $form = $this->formFactory->create(AccountType::class, $account);

        $this->assertTrue($form->get('code')->isDisabled());
    }

    public function testEnvironmentChoicesAreCorrect(): void
    {
        $form = $this->formFactory->create(AccountType::class, new Account());

        $choices = $form->get('environment')->getConfig()->getOption('choices');

        $this->assertArrayHasKey('sylius_hipay_plugin.ui.test', $choices);
        $this->assertArrayHasKey('sylius_hipay_plugin.ui.production', $choices);
        $this->assertSame(AccountInterface::ENVIRONMENT_TEST, $choices['sylius_hipay_plugin.ui.test']);
        $this->assertSame(AccountInterface::ENVIRONMENT_PRODUCTION, $choices['sylius_hipay_plugin.ui.production']);
    }

    public function testEmptyPasswordOnEditKeepsThePersistedValue(): void
    {
        $account = new Account();
        $account->setName('Existing');
        $account->setCode('existing-code');
        $account->setEnvironment(AccountInterface::ENVIRONMENT_TEST);
        $account->setApiPassword('stored-prod-pass');
        $account->setSecretPassphrase('stored-prod-secret');

        (new ReflectionProperty(Account::class, 'id'))->setValue($account, 7);

        $form = $this->formFactory->create(AccountType::class, $account);
        $form->submit([
            'name' => 'Existing',
            'environment' => AccountInterface::ENVIRONMENT_TEST,
            'apiUsername' => 'prod_user',
            'apiPassword' => '',
            'secretPassphrase' => '',
            'testApiUsername' => 'test_user',
            'testApiPassword' => 'test_pass',
            'testSecretPassphrase' => 'test_secret',
            'debugMode' => true,
        ]);

        $this->assertTrue($form->isSynchronized());

        /** @var Account $updated */
        $updated = $form->getData();
        // Empty submissions must NOT blank the stored secrets.
        $this->assertSame('stored-prod-pass', $updated->getApiPassword());
        $this->assertSame('stored-prod-secret', $updated->getSecretPassphrase());
    }

    public function testFilledPasswordOnEditIsUpdated(): void
    {
        $account = new Account();
        $account->setName('Existing');
        $account->setCode('existing-code');
        $account->setEnvironment(AccountInterface::ENVIRONMENT_TEST);
        $account->setApiPassword('stored-prod-pass');

        (new ReflectionProperty(Account::class, 'id'))->setValue($account, 8);

        $form = $this->formFactory->create(AccountType::class, $account);
        $form->submit([
            'name' => 'Existing',
            'environment' => AccountInterface::ENVIRONMENT_TEST,
            'apiUsername' => 'prod_user',
            'apiPassword' => 'new-prod-pass',
            'testApiUsername' => 'test_user',
            'testApiPassword' => 'test_pass',
            'testSecretPassphrase' => 'test_secret',
            'debugMode' => false,
        ]);

        $this->assertTrue($form->isSynchronized());
        // A non-empty submission still updates the credential.
        $this->assertSame('new-prod-pass', $form->getData()->getApiPassword());
    }

    public function testProductionAccountRequiresProductionCredentials(): void
    {
        /** @var ValidatorInterface $validator */
        $validator = self::getContainer()->get('validator');

        $account = new Account();
        $account->setName('New Prod');
        $account->setCode('new-prod');
        $account->setEnvironment(AccountInterface::ENVIRONMENT_PRODUCTION);
        // Production credentials deliberately left empty.

        $violations = $validator->validate($account, null, ['sylius_hipay_plugin']);

        $paths = array_map(
            static fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath(),
            iterator_to_array($violations),
        );

        // The environment-based NotBlank checks fire for the production credentials.
        $this->assertContains('apiPassword', $paths);
        $this->assertContains('secretPassphrase', $paths);
        $this->assertContains('apiUsername', $paths);
    }

    public function testSwitchingEnvironmentWipesTheOtherEnvironmentPasswords(): void
    {
        $account = new Account();
        $account->setName('Existing');
        $account->setCode('existing-code');
        $account->setEnvironment(AccountInterface::ENVIRONMENT_TEST);
        // Both environments start with stored secrets.
        $account->setApiPassword('prod-pass');
        $account->setSecretPassphrase('prod-secret');
        $account->setPublicPassword('prod-public');
        $account->setTestApiPassword('test-pass');
        $account->setTestSecretPassphrase('test-secret');
        $account->setTestPublicPassword('test-public');

        (new ReflectionProperty(Account::class, 'id'))->setValue($account, 9);

        $form = $this->formFactory->create(AccountType::class, $account);
        $form->submit([
            'name' => 'Existing',
            'environment' => AccountInterface::ENVIRONMENT_PRODUCTION, // test -> production
            'apiUsername' => 'prod_user',
            'apiPassword' => '',
            'secretPassphrase' => '',
            'publicUsername' => 'prod_public_user',
            'publicPassword' => '',
            'testApiUsername' => 'test_user',
            'testApiPassword' => '',
            'testSecretPassphrase' => '',
            'testPublicUsername' => 'test_public_user',
            'testPublicPassword' => '',
            'debugMode' => false,
        ]);

        $this->assertTrue($form->isSynchronized());

        /** @var Account $updated */
        $updated = $form->getData();
        // Production (now active) secrets are kept via re-injection.
        $this->assertSame('prod-pass', $updated->getApiPassword());
        $this->assertSame('prod-secret', $updated->getSecretPassphrase());
        $this->assertSame('prod-public', $updated->getPublicPassword());
        // Test (left) secrets are wiped.
        $this->assertSame('', $updated->getTestApiPassword());
        $this->assertSame('', $updated->getTestSecretPassphrase());
        $this->assertSame('', $updated->getTestPublicPassword());
    }
}
