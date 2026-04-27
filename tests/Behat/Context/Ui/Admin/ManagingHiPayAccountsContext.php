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

namespace Tests\HiPay\SyliusHiPayPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Entity\Account;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use Sylius\Behat\Page\Admin\Crud\CreatePageInterface;
use Sylius\Behat\Page\Admin\Crud\IndexPageInterface;
use Sylius\Behat\Page\Admin\Crud\UpdatePageInterface;
use Tests\HiPay\SyliusHiPayPlugin\Behat\Element\Admin\Account\FormElementInterface;
use Webmozart\Assert\Assert;

final readonly class ManagingHiPayAccountsContext implements Context
{
    public function __construct(
        private IndexPageInterface $indexPage,
        private CreatePageInterface $createPage,
        private UpdatePageInterface $updatePage,
        private FormElementInterface $formElement,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @Given there is a HiPay account :name with code :code
     */
    public function thereIsAHiPayAccountWithCode(string $name, string $code): void
    {
        $account = new Account();
        $account->setName($name);
        $account->setCode($code);
        $account->setEnvironment(AccountInterface::ENVIRONMENT_TEST);
        $account->setApiUsername('api_user');
        $account->setApiPassword('api_pass');
        $account->setSecretPassphrase('secret');
        $account->setTestApiUsername('test_user');
        $account->setTestApiPassword('test_pass');
        $account->setTestSecretPassphrase('test_secret');

        $this->entityManager->persist($account);
        $this->entityManager->flush();
    }

    /**
     * @When I browse HiPay accounts
     */
    public function iBrowseHiPayAccounts(): void
    {
        $this->indexPage->open();
    }

    /**
     * @When I want to create a new HiPay account
     */
    public function iWantToCreateANewHiPayAccount(): void
    {
        $this->createPage->open();
    }

    /**
     * @When I want to modify the HiPay account :name
     */
    public function iWantToModifyTheHiPayAccount(string $name): void
    {
        $account = $this->entityManager->getRepository(Account::class)->findOneBy(['name' => $name]);
        Assert::notNull($account);

        $this->updatePage->open(['id' => $account->getId()]);
    }

    /**
     * @When I name it :name
     * @When I do not name it
     */
    public function iNameIt(?string $name = null): void
    {
        $this->formElement->setName($name ?? '');
    }

    /**
     * @When I rename it to :name
     */
    public function iRenameItTo(string $name): void
    {
        $this->formElement->setName($name);
    }

    /**
     * @When I specify its code as :code
     * @When I do not specify its code
     */
    public function iSpecifyItsCodeAs(?string $code = null): void
    {
        $this->formElement->setCode($code ?? '');
    }

    /**
     * @When I set its environment to :environment
     */
    public function iSetItsEnvironmentTo(string $environment): void
    {
        $this->formElement->setEnvironment($environment);
    }

    /**
     * @When I set the API username to :value
     */
    public function iSetTheApiUsernameTo(string $value): void
    {
        $this->formElement->setApiUsername($value);
    }

    /**
     * @When I set the API password to :value
     */
    public function iSetTheApiPasswordTo(string $value): void
    {
        $this->formElement->setApiPassword($value);
    }

    /**
     * @When I set the secret passphrase to :value
     */
    public function iSetTheSecretPassphraseTo(string $value): void
    {
        $this->formElement->setSecretPassphrase($value);
    }

    /**
     * @When I set the test API username to :value
     */
    public function iSetTheTestApiUsernameTo(string $value): void
    {
        $this->formElement->setTestApiUsername($value);
    }

    /**
     * @When I set the test API password to :value
     */
    public function iSetTheTestApiPasswordTo(string $value): void
    {
        $this->formElement->setTestApiPassword($value);
    }

    /**
     * @When I set the test secret passphrase to :value
     */
    public function iSetTheTestSecretPassphraseTo(string $value): void
    {
        $this->formElement->setTestSecretPassphrase($value);
    }

    /**
     * @When I set the test API public username to :value
     */
    public function iSetTheTestPublicUsernameTo(string $value): void
    {
        $this->formElement->setTestPublicUsername($value);
    }

    /**
     * @When I set the test API public password to :value
     */
    public function iSetTheTestPublicPasswordTo(string $value): void
    {
        $this->formElement->setTestPublicPassword($value);
    }

    /**
     * @When I add it
     * @When I try to add it
     */
    public function iAddIt(): void
    {
        $this->createPage->create();
    }

    /**
     * @When I delete the HiPay account :name
     */
    public function iDeleteTheHiPayAccount(string $name): void
    {
        $this->indexPage->deleteResourceOnPage(['name' => $name]);
    }

    /**
     * @Then I should see :count HiPay account(s) in the list
     */
    public function iShouldSeeHiPayAccountsInTheList(int $count): void
    {
        Assert::same($this->indexPage->countItems(), $count);
    }

    /**
     * @Then I should see the HiPay account :name in the list
     * @Then the HiPay account :name should appear in the list
     */
    public function iShouldSeeTheHiPayAccountInTheList(string $name): void
    {
        $this->indexPage->open();
        Assert::true($this->indexPage->isSingleResourceOnPage(['name' => $name]));
    }

    /**
     * @Then the HiPay account with code :code should appear in the list
     */
    public function theHiPayAccountWithCodeShouldAppearInTheList(string $code): void
    {
        $this->indexPage->open();
        Assert::true($this->indexPage->isSingleResourceOnPage(['code' => $code]));
    }

    /**
     * @Then the HiPay account :name should no longer exist in the list
     */
    public function theHiPayAccountShouldNoLongerExistInTheList(string $name): void
    {
        $this->entityManager->clear();
        $account = $this->entityManager->getRepository(Account::class)->findOneBy(['name' => $name]);
        Assert::null($account, sprintf('HiPay account "%s" still exists in the database.', $name));
    }

    /**
     * @Then I should be notified that the form contains errors
     */
    public function iShouldBeNotifiedThatTheFormContainsErrors(): void
    {
        Assert::true($this->formElement->hasFormErrorAlert());
    }

    /**
     * @Then the code field should be disabled
     * @Then I should not be able to edit its code
     */
    public function theCodeFieldShouldBeDisabled(): void
    {
        Assert::true($this->formElement->isCodeDisabled());
    }

    /**
     * @Then I should be notified that :element is required
     */
    public function iShouldBeNotifiedThatIsRequired(string $element): void
    {
        Assert::contains(
            $this->formElement->getValidationMessage($element),
            'blank',
        );
    }
}
