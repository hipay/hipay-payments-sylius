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

namespace Tests\HiPay\SyliusHiPayPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Entity\Account;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;

final readonly class HiPayAccountContext implements Context
{
    public function __construct(
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
}
