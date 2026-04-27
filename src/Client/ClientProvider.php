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

namespace HiPay\SyliusHiPayPlugin\Client;

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use function is_string;
use RuntimeException;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class ClientProvider implements ClientProviderInterface
{
    public function __construct(private readonly RepositoryInterface $accountRepository)
    {
    }

    public function getForPaymentMethod(PaymentMethodInterface $paymentMethod): HiPayClientInterface
    {
        $accountCode = $paymentMethod->getGatewayConfig()?->getConfig()['account'] ?? null;
        if (!is_string($accountCode) || '' === $accountCode) {
            throw new RuntimeException('Account code is not defined');
        }

        return $this->getForAccountCode($accountCode);
    }

    public function getForAccountCode(string $accountCode): HiPayClientInterface
    {
        /** @var ?AccountInterface $account */
        $account = $this->accountRepository->findOneBy(['code' => $accountCode]);
        if (null === $account) {
            throw new RuntimeException(sprintf('Account with code "%s" not found', $accountCode));
        }

        return new HiPayClient(
            $account->getApiUsernameForCurrentEnv(),
            $account->getApiPasswordForCurrentEnv(),
            $account->getEnvironment(),
        );
    }

    public function getForAccount(AccountInterface $account): HiPayClientInterface
    {
        return new HiPayClient(
            $account->getApiUsernameForCurrentEnv(),
            $account->getApiPasswordForCurrentEnv(),
            $account->getEnvironment(),
        );
    }
}
