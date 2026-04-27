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

namespace Tests\HiPay\SyliusHiPayPlugin\Integration\Doctrine;

use DateTimeInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Entity\Account;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AccountMappingTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();
        parent::tearDown();
    }

    public function testAccountMetadataIsLoaded(): void
    {
        $metadata = $this->entityManager->getClassMetadata(Account::class);

        $this->assertSame('hipay_account', $metadata->getTableName());
        $this->assertTrue($metadata->hasField('name'));
        $this->assertTrue($metadata->hasField('code'));
        $this->assertTrue($metadata->hasField('apiUsername'));
        $this->assertTrue($metadata->hasField('apiPassword'));
        $this->assertTrue($metadata->hasField('secretPassphrase'));
        $this->assertTrue($metadata->hasField('testApiUsername'));
        $this->assertTrue($metadata->hasField('testApiPassword'));
        $this->assertTrue($metadata->hasField('testSecretPassphrase'));
        $this->assertTrue($metadata->hasField('environment'));
        $this->assertTrue($metadata->hasField('debugMode'));
        $this->assertTrue($metadata->hasField('createdAt'));
        $this->assertTrue($metadata->hasField('updatedAt'));
    }

    public function testAccountCanBePersistedAndRetrieved(): void
    {
        $account = $this->createAccount('persist-test', 'persist_test_code');

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        $this->assertNotNull($account->getId());

        $this->entityManager->clear();

        $found = $this->entityManager->find(Account::class, $account->getId());

        $this->assertInstanceOf(Account::class, $found);
        $this->assertSame('persist-test', $found->getName());
        $this->assertSame('persist_test_code', $found->getCode());
        $this->assertSame('api_user', $found->getApiUsername());
        $this->assertSame('api_pass', $found->getApiPassword());
        $this->assertSame('secret', $found->getSecretPassphrase());
        $this->assertSame('test_user', $found->getTestApiUsername());
        $this->assertSame('test_pass', $found->getTestApiPassword());
        $this->assertSame('test_secret', $found->getTestSecretPassphrase());
        $this->assertSame(AccountInterface::ENVIRONMENT_TEST, $found->getEnvironment());
        $this->assertFalse($found->isDebugMode());
    }

    public function testCreatedAtIsSetAutomatically(): void
    {
        $account = $this->createAccount('timestamp-test', 'timestamp_code');

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        $this->assertInstanceOf(DateTimeInterface::class, $account->getCreatedAt());
    }

    public function testCodeUniqueConstraintIsEnforced(): void
    {
        $account1 = $this->createAccount('First', 'unique_code');
        $account2 = $this->createAccount('Second', 'unique_code');

        $this->entityManager->persist($account1);
        $this->entityManager->flush();

        $this->entityManager->persist($account2);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    private function createAccount(string $name, string $code): Account
    {
        $account = new Account();
        $account->setName($name);
        $account->setCode($code);
        $account->setApiUsername('api_user');
        $account->setApiPassword('api_pass');
        $account->setSecretPassphrase('secret');
        $account->setTestApiUsername('test_user');
        $account->setTestApiPassword('test_pass');
        $account->setTestSecretPassphrase('test_secret');
        $account->setEnvironment(AccountInterface::ENVIRONMENT_TEST);
        $account->setDebugMode(false);

        return $account;
    }
}
