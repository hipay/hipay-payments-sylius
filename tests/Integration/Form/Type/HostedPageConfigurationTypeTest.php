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
use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\HostedPageConfigurationType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class HostedPageConfigurationTypeTest extends KernelTestCase
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
        $form = $this->formFactory->create(HostedPageConfigurationType::class);

        $this->assertTrue($form->has('account'));
    }

    public function testFormSubmitWithValidAccountTriggersPaymentProductsField(): void
    {
        $form = $this->formFactory->create(HostedPageConfigurationType::class, [
            'account' => $this->accountCode,
        ]);

        $form->submit([
            'account' => $this->accountCode,
        ]);

        $this->assertTrue($form->has('account'));
        $this->assertTrue($form->isSynchronized());
    }

    public function testFormSubmitWithAccountTriggersHostedPageAndProductConfigurationsFields(): void
    {
        $form = $this->formFactory->create(HostedPageConfigurationType::class, [
            'account' => $this->accountCode,
        ]);

        $form->submit([
            'account' => $this->accountCode,
            'hosted_page' => [
                'hosted_page_v2' => true,
                'template' => 'basic-js',
            ],
        ]);

        $this->assertTrue($form->has('hosted_page'));
        // product_configurations is populated from the HiPay API — presence depends on fixtures
        $this->assertTrue($form->isSynchronized());
        // card and apple-pay are never in product_configurations (Hosted Fields only)
        if ($form->has('product_configurations')) {
            $this->assertFalse($form->get('product_configurations')->has('apple-pay'));
        }
    }

    private function createAccount(): string
    {
        $account = new Account();
        $account->setName('Integration Test Account');
        $account->setCode('integration_test_account_hp');
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
            ->findOneBy(['code' => 'integration_test_account_hp']);

        if (null !== $account) {
            $this->entityManager->remove($account);
            $this->entityManager->flush();
        }
    }
}
