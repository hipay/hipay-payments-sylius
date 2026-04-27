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

use HiPay\SyliusHiPayPlugin\Entity\TransactionInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Payment\Model\PaymentInterface;

final class TransactionProvider implements TransactionProviderInterface
{
    public function __construct(
        private readonly EntityRepository $hiPayTransactionRepository,
    ) {
    }

    public function getByTransactionReference(string $transactionReference): ?TransactionInterface
    {
        /** @var TransactionInterface|null $hipayTransaction */
        $hipayTransaction = $this->hiPayTransactionRepository->findOneBy(['transactionReference' => $transactionReference]);

        return $hipayTransaction;
    }

    public function getByPayment(PaymentInterface $payment): ?TransactionInterface
    {
        /** @var TransactionInterface|null $hipayTransaction */
        $hipayTransaction = $this->hiPayTransactionRepository->findOneBy(['payment' => $payment]);

        return $hipayTransaction;
    }
}
