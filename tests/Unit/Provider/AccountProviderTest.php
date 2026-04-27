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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Provider;

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Provider\AccountProvider;
use HiPay\SyliusHiPayPlugin\Provider\TransactionProviderInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;

final class AccountProviderTest extends TestCase
{
    public function testGetForChoiceListReturnsNameCodeMapping(): void
    {
        $account1 = $this->createMock(AccountInterface::class);
        $account1->method('getName')->willReturn('Production');
        $account1->method('getCode')->willReturn('prod-1');

        $account2 = $this->createMock(AccountInterface::class);
        $account2->method('getName')->willReturn('Test');
        $account2->method('getCode')->willReturn('test-1');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findAll')->willReturn([$account1, $account2]);

        $transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $provider = new AccountProvider($repository, $transactionProvider);

        $choices = $provider->getForChoiceList();

        $this->assertSame(['Production' => 'prod-1', 'Test' => 'test-1'], $choices);
    }

    public function testGetForChoiceListReturnsEmptyWhenNoAccounts(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findAll')->willReturn([]);

        $transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $provider = new AccountProvider($repository, $transactionProvider);

        $this->assertSame([], $provider->getForChoiceList());
    }

    public function testGetByCodeReturnsAccount(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('getCode')->willReturn('prod-1');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->with(['code' => 'prod-1'])->willReturn($account);

        $transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $provider = new AccountProvider($repository, $transactionProvider);

        $this->assertSame($account, $provider->getByCode('prod-1'));
    }

    public function testGetByCodeReturnsNullWhenNotFound(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->with(['code' => 'unknown'])->willReturn(null);

        $transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $provider = new AccountProvider($repository, $transactionProvider);

        $this->assertNull($provider->getByCode('unknown'));
    }

    public function testGetByPaymentMethodReturnsAccountFromGatewayConfig(): void
    {
        $account = $this->createMock(AccountInterface::class);

        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn(['account' => 'prod-1']);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->with(['code' => 'prod-1'])->willReturn($account);

        $transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $provider = new AccountProvider($repository, $transactionProvider);

        $this->assertSame($account, $provider->getByPaymentMethod($paymentMethod));
    }

    public function testGetByPaymentMethodReturnsNullWhenNoGatewayConfig(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn(null);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('findOneBy');

        $transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $provider = new AccountProvider($repository, $transactionProvider);

        $this->assertNull($provider->getByPaymentMethod($paymentMethod));
    }

    public function testGetByPaymentMethodReturnsNullWhenNoAccountCode(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn([]);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('findOneBy');

        $transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $provider = new AccountProvider($repository, $transactionProvider);

        $this->assertNull($provider->getByPaymentMethod($paymentMethod));
    }
}
