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
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\PaymentInterface;

final readonly class CancelPaymentContext implements Context
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @Given there is an order with token :tokenValue
     */
    public function thereIsAnOrderWithToken(string $tokenValue): void
    {
        $order = new Order();
        $order->setTokenValue($tokenValue);
        $order->setLocaleCode('en_US');
        $order->setCurrencyCode('EUR');
        $order->setState(OrderInterface::STATE_NEW);

        $this->entityManager->persist($order);
        $this->entityManager->flush();
    }

    /**
     * @Given there is an order with token :tokenValue and a payment in processing state
     */
    public function thereIsAnOrderWithTokenAndAPaymentInProcessingState(string $tokenValue): void
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setCode('hipay_test_' . $tokenValue);
        $paymentMethod->setCurrentLocale('en_US');
        $paymentMethod->setFallbackLocale('en_US');
        $paymentMethod->setName('HiPay Test');
        $this->entityManager->persist($paymentMethod);

        $order = new Order();
        $order->setTokenValue($tokenValue);
        $order->setLocaleCode('en_US');
        $order->setCurrencyCode('EUR');
        $order->setState(OrderInterface::STATE_NEW);
        $this->entityManager->persist($order);

        $payment = new Payment();
        $payment->setMethod($paymentMethod);
        $order->addPayment($payment);
        $payment->setCurrencyCode('EUR');
        $payment->setAmount(1000);
        $this->entityManager->persist($payment);

        $payment->setState(PaymentInterface::STATE_PROCESSING);
        $this->entityManager->flush();
    }
}
