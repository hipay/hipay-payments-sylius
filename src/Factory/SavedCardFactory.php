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

namespace HiPay\SyliusHiPayPlugin\Factory;

use HiPay\SyliusHiPayPlugin\Entity\SavedCardInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

class SavedCardFactory implements SavedCardFactoryInterface
{
    public function __construct(
        private readonly FactoryInterface $decoratedFactory,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function createNew(): SavedCardInterface
    {
        // @phpstan-ignore-next-line
        return $this->decoratedFactory->createNew();
    }

    public function createNewFromPayment(PaymentInterface $payment): ?SavedCardInterface
    {
        /** @var array<int|string> $paymentDetails */
        $paymentDetails = $payment->getDetails();
        $pan = $paymentDetails['pan'] ?? null;
        $token = $paymentDetails['token'] ?? null;
        $brand = $paymentDetails['brand'] ?? null;
        $expiryMonth = $paymentDetails['card_expiry_month'] ?? null;
        $expiryYear = $paymentDetails['card_expiry_year'] ?? null;
        $holder = $paymentDetails['card_holder'] ?? null;
        // @phpstan-ignore-next-line
        $customer = $payment->getOrder()?->getCustomer();
        if (null === $pan || null === $token || null === $brand || null === $expiryMonth || null === $expiryYear || null === $customer || null === $holder) {
            return null;
        }
        /** @var SavedCardInterface $savedCard */
        $savedCard = $this->createNew();
        $savedCard->setToken((string) $token);
        $savedCard->setCustomer($customer);
        $savedCard->setMaskedPan((string) $pan);
        $savedCard->setBrand(str_replace(' ', '-', strtolower((string) $brand)));
        $savedCard->setExpiryMonth((string) $expiryMonth);
        $savedCard->setExpiryYear((string) $expiryYear);
        $savedCard->setHolder((string) $holder);
        $savedCard->setAuthorized(false);

        return $savedCard;
    }
}
