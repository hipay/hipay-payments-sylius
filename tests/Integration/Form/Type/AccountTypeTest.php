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
}
