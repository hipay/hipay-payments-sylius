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

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Entity\Account;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\HostedFieldsConfigurationType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class HostedFieldsConfigurationTypeTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;

    private EntityManagerInterface $entityManager;

    private string $accountCode;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->formFactory = self::getContainer()->get('form.factory');
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        $this->accountCode = $this->createAccount();
    }

    protected function tearDown(): void
    {
        $this->removeAccount();

        parent::tearDown();
    }

    public function testFormHasAccountField(): void
    {
        $form = $this->formFactory->create(HostedFieldsConfigurationType::class);

        $this->assertTrue($form->has('account'));
    }

    public function testFormSubmitWithValidAccountTriggersPaymentProductField(): void
    {
        $form = $this->formFactory->create(HostedFieldsConfigurationType::class, [
            'account' => $this->accountCode,
        ]);

        $form->submit([
            'account' => $this->accountCode,
        ]);

        $this->assertTrue($form->has('account'));
        $this->assertTrue($form->isSynchronized());
    }

    public function testFormSubmitWithCardPaymentProductTriggersConfigurationField(): void
    {
        $form = $this->formFactory->create(HostedFieldsConfigurationType::class, [
            'account' => $this->accountCode,
            'payment_product' => 'card',
        ]);

        $form->submit([
            'account' => $this->accountCode,
            'payment_product' => 'card',
        ]);

        $this->assertTrue($form->has('account'));
        $this->assertTrue($form->isSynchronized());
    }

    private function createAccount(): string
    {
        $account = new Account();
        $account->setName('Integration Test Account');
        $account->setCode('integration_test_account');
        $account->setEnvironment(AccountInterface::ENVIRONMENT_TEST);
        $account->setApiUsername('api_user');
        $account->setApiPassword('api_pass');
        $account->setSecretPassphrase('secret');
        $account->setTestApiUsername('test_user');
        $account->setTestApiPassword('test_pass');
        $account->setTestSecretPassphrase('test_secret');

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return (string) $account->getCode();
    }

    private function removeAccount(): void
    {
        $account = $this->entityManager
            ->getRepository(Account::class)
            ->findOneBy(['code' => 'integration_test_account']);

        if (null !== $account) {
            $this->entityManager->remove($account);
            $this->entityManager->flush();
        }
    }
}
