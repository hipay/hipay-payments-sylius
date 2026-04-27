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

namespace HiPay\SyliusHiPayPlugin\Provider;

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

/**
 * Provides available accounts for the Hosted Fields gateway configuration.
 */
final class AccountProvider implements AccountProviderInterface
{
    public function __construct(
        private readonly EntityRepository $accountRepository,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getForChoiceList(): array
    {
        $accounts = [];
        /** @var AccountInterface $account */
        foreach ($this->accountRepository->findAll() as $account) {
            $accounts[$account->getName()] = $account->getCode();
        }

        return $accounts;
    }

    public function getById(int $id): ?AccountInterface
    {
        /** @var AccountInterface|null $account */
        $account = $this->accountRepository->find($id);

        return $account;
    }

    public function getByCode(string $code): ?AccountInterface
    {
        /** @var AccountInterface|null $account */
        $account = $this->accountRepository->findOneBy(['code' => $code]);

        return $account;
    }

    public function getByPaymentMethod(PaymentMethodInterface $paymentMethod): ?AccountInterface
    {
        /** @var string|null $code */
        $code = $paymentMethod->getGatewayConfig()?->getConfig()['account'] ?? null;
        if (null === $code) {
            return null;
        }

        return $this->getByCode($code);
    }

    public function getByPayment(PaymentInterface $payment): ?AccountInterface
    {
        /** @var string|null $code */
        $code = $payment->getMethod()?->getGatewayConfig()?->getConfig()['account'] ?? null;
        if (null === $code) {
            return null;
        }

        return $this->getByCode($code);
    }
}
