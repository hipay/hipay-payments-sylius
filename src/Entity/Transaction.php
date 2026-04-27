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

namespace HiPay\SyliusHiPayPlugin\Entity;

use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\Index;
use Sylius\Resource\Model\TimestampableTrait;

#[AsResource(
    alias: 'sylius_hipay_plugin.transaction',
    section: 'admin',
    routePrefix: '/hipay',
    name: 'transaction',
    pluralName: 'transactions',
    applicationName: 'sylius_hipay_plugin',
    vars: [
        'subheader' => 'sylius_hipay_plugin.ui.transactions',
    ],
    operations: [
        new Index(
            vars: ['header' => 'sylius_hipay_plugin.ui.transactions'],
            grid: 'hipay_admin_transaction',
        ),
    ],
)]
class Transaction implements TransactionInterface
{
    use TimestampableTrait;

    private ?int $id = null;

    protected ?string $transactionReference = null;

    protected ?PaymentInterface $payment;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTransactionReference(): ?string
    {
        return $this->transactionReference;
    }

    public function setTransactionReference(?string $transactionReference): void
    {
        $this->transactionReference = $transactionReference;
    }

    public function getPayment(): ?PaymentInterface
    {
        return $this->payment;
    }

    public function setPayment(?PaymentInterface $payment): void
    {
        $this->payment = $payment;
    }
}
