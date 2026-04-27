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

namespace Tests\HiPay\SyliusHiPayPlugin\Integration\Processor;

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Entity\Account;
use HiPay\SyliusHiPayPlugin\Entity\Transaction;
use HiPay\SyliusHiPayPlugin\Webhook\NotificationProcessorInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig as PayumGatewayConfig;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\Channel as CoreChannel;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod as CorePaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodTranslation;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test: an order payment is driven through HiPay notification statuses
 * via NotificationProcessor, following the transaction lifecycle from HiPay documentation.
 * Statuses without a Sylius transition (e.g. 142, 124) are informational: they persist
 * PaymentRequest data but do not change the payment state machine.
 *
 * @see https://developer.hipay.com/payment-fundamentals/essentials/transaction-status
 * @see https://developer.hipay.com/payment-fundamentals/essentials/transaction-lifecycle
 */
final class NotificationProcessorOrderLifecycleTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private NotificationProcessorInterface $notificationProcessor;

    private string $transactionReference;

    private OrderInterface $order;

    private PaymentInterface $payment;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get('doctrine.orm.entity_manager');
        $this->notificationProcessor = $container->get(NotificationProcessorInterface::class);

        $this->entityManager->beginTransaction();

        $this->transactionReference = 'TEST-TXN-HIPAY';
        $this->order = $this->createOrderWithPayment();
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();
        parent::tearDown();
    }

    /**
     * Full lifecycle: pending → authorized → captured (success path from HiPay doc).
     */
    public function testPaymentLifecycleFromAuthorizationToCaptured(): void
    {
        $this->assertPaymentState(BasePaymentInterface::STATE_NEW);

        // 101 Transaction Created – informational, no state change
        $this->processNotification(101, 'notif-101');
        $this->assertPaymentState(BasePaymentInterface::STATE_NEW);

        // 142 Authorization Requested – informational (no Sylius transition); payment stays new until authorize
        $this->processNotification(142, 'notif-142');
        $this->assertPaymentState(BasePaymentInterface::STATE_NEW);

        // 140 Authentication Requested – informational, no state change
        $this->processNotification(140, 'notif-140');
        $this->assertPaymentState(BasePaymentInterface::STATE_NEW);

        // 116 Authorized – financial institution approved the payment
        $this->processNotification(116, 'notif-116');
        $this->assertPaymentState(BasePaymentInterface::STATE_AUTHORIZED);

        // 117 Capture Requested – informational only, no state transition triggered
        $this->processNotification(117, 'notif-117');
        $this->assertPaymentState(BasePaymentInterface::STATE_AUTHORIZED);

        // 118 Captured – financial institution processed the payment
        $this->processNotification(118, 'notif-118');
        $this->assertPaymentState(BasePaymentInterface::STATE_COMPLETED);
    }

    /**
     * Failure path: payment is declined (e.g. 113 Refused).
     */
    public function testPaymentLifecycleDeclined(): void
    {
        $this->assertPaymentState(BasePaymentInterface::STATE_NEW);

        $this->processNotification(142, 'notif-decline-1');
        $this->assertPaymentState(BasePaymentInterface::STATE_NEW);

        $this->processNotification(113, 'notif-decline-113'); // Refused
        $this->assertPaymentState(BasePaymentInterface::STATE_FAILED);
    }

    /**
     * Cancellation path: authorized payment receives 115 (Cancelled).
     * Status 115 is a customer-initiated cancellation (e.g. iDEAL
     * "Cancelled by customer") and maps to the `cancel` transition
     * so the admin sees "Cancelled" instead of "Failed".
     */
    public function testPaymentLifecycleCancelled(): void
    {
        $this->assertPaymentState(BasePaymentInterface::STATE_NEW);

        $this->processNotification(142, 'notif-cancel-1');
        $this->processNotification(116, 'notif-cancel-116');
        $this->assertPaymentState(BasePaymentInterface::STATE_AUTHORIZED);

        $this->processNotification(115, 'notif-cancel-115');
        $this->assertPaymentState(BasePaymentInterface::STATE_CANCELLED);
    }

    /**
     * Refund path: captured payment is refunded (124 → 125).
     */
    public function testPaymentLifecycleRefunded(): void
    {
        $this->assertPaymentState(BasePaymentInterface::STATE_NEW);

        $this->processNotification(142, 'notif-refund-1');
        $this->processNotification(116, 'notif-refund-116');
        $this->processNotification(118, 'notif-refund-118');
        $this->assertPaymentState(BasePaymentInterface::STATE_COMPLETED);

        // 124 Refund Requested – informational (no transition); still completed until 125
        $this->processNotification(124, 'notif-refund-124');
        $this->assertPaymentState(BasePaymentInterface::STATE_COMPLETED);

        $this->processNotification(125, 'notif-refund-125'); // Refunded
        $this->assertPaymentState(BasePaymentInterface::STATE_REFUNDED);
    }

    private function processNotification(int $status, string $mid): void
    {
        $this->notificationProcessor->process('event-' . $mid, [
            'transaction_reference' => $this->transactionReference,
            'status' => $status,
            'mid' => $mid,
            'state' => $this->getHiPayStateForStatus($status),
        ]);

        $this->entityManager->clear();
        $this->refreshPayment();
    }

    private function assertPaymentState(string $expectedState): void
    {
        $this->refreshPayment();
        self::assertSame(
            $expectedState,
            $this->payment->getState(),
            sprintf('Expected payment state "%s", got "%s".', $expectedState, $this->payment->getState()),
        );
    }

    private function refreshPayment(): void
    {
        $orderRepo = $this->entityManager->getRepository($this->order::class);
        $this->order = $orderRepo->find($this->order->getId());
        self::assertInstanceOf(OrderInterface::class, $this->order);
        $payment = $this->order->getLastPayment();
        self::assertInstanceOf(PaymentInterface::class, $payment);
        $this->payment = $payment;
    }

    private function createOrderWithPayment(): OrderInterface
    {
        $this->getOrCreateAccount('test_account');
        $channel = $this->getOrCreateChannel();
        $paymentMethod = $this->getOrCreatePaymentMethod($channel);

        $orderFactory = self::getContainer()->get('sylius.factory.order');
        $paymentFactory = self::getContainer()->get('sylius.factory.payment');

        /** @var OrderInterface $order */
        $order = $orderFactory->createNew();
        $order->setChannel($channel);
        $order->setLocaleCode('en_US');
        $order->setCurrencyCode('EUR');
        $order->setTokenValue('ORDER-TEST-TXN-HIPAY');

        /** @var PaymentInterface $payment */
        $payment = $paymentFactory->createNew();
        $payment->setOrder($order);
        $payment->setMethod($paymentMethod);
        $payment->setAmount(1500); // 15.00 EUR in cents
        $payment->setCurrencyCode('EUR');
        $payment->setState(BasePaymentInterface::STATE_NEW);

        $order->addPayment($payment);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $transaction = new Transaction();
        $transaction->setTransactionReference($this->transactionReference);
        $transaction->setPayment($payment);
        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        $this->payment = $payment;

        return $order;
    }

    private function getOrCreateAccount(string $code): Account
    {
        $repo = $this->entityManager->getRepository(Account::class);
        /** @var Account|null $account */
        $account = $repo->findOneBy(['code' => $code]);
        if (null !== $account) {
            return $account;
        }

        $account = new Account();
        $account->setCode($code);
        $account->setName('Test Account');
        $account->setApiUsername('api_user');
        $account->setApiPassword('api_pass');
        $account->setSecretPassphrase('secret');
        $account->setTestApiUsername('test_api_user');
        $account->setTestApiPassword('test_api_pass');
        $account->setTestSecretPassphrase('test_secret');
        $account->setEnvironment(Account::ENVIRONMENT_TEST);
        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return $account;
    }

    private function getOrCreateChannel(): ChannelInterface
    {
        $channelRepo = $this->entityManager->getRepository(CoreChannel::class);
        $channel = $channelRepo->findOneBy([]);

        if (null !== $channel) {
            return $channel;
        }

        $locale = $this->getOrCreateLocale('en_US');
        $currency = $this->getOrCreateCurrency('EUR');

        $channel = new CoreChannel();
        $channel->setCode('WEB');
        $channel->setName('Web Store');
        $channel->setTaxCalculationStrategy('order_items_based');
        $channel->addLocale($locale);
        $channel->setDefaultLocale($locale);
        $channel->addCurrency($currency);
        $channel->setBaseCurrency($currency);
        $this->entityManager->persist($channel);
        $this->entityManager->flush();

        return $channel;
    }

    private function getOrCreateLocale(string $code): LocaleInterface
    {
        $repo = $this->entityManager->getRepository(Locale::class);
        $locale = $repo->findOneBy(['code' => $code]);
        if (null !== $locale) {
            return $locale;
        }
        $locale = new Locale();
        $locale->setCode($code);
        $this->entityManager->persist($locale);
        $this->entityManager->flush();

        return $locale;
    }

    private function getOrCreateCurrency(string $code): CurrencyInterface
    {
        $repo = $this->entityManager->getRepository(Currency::class);
        $currency = $repo->findOneBy(['code' => $code]);
        if (null !== $currency) {
            return $currency;
        }
        $currency = new Currency();
        $currency->setCode($code);
        $this->entityManager->persist($currency);
        $this->entityManager->flush();

        return $currency;
    }

    private function getOrCreatePaymentMethod(ChannelInterface $channel): PaymentMethodInterface
    {
        $pmRepo = $this->entityManager->getRepository(CorePaymentMethod::class);
        $paymentMethod = $pmRepo->findOneBy(['code' => 'hipay_test']);

        if (null !== $paymentMethod) {
            $paymentMethod->setCurrentLocale('en_US');

            return $paymentMethod;
        }

        $gatewayConfig = new PayumGatewayConfig();
        $gatewayConfig->setGatewayName('hipay_hosted_fields');
        $gatewayConfig->setFactoryName('hipay_hosted_fields');
        $gatewayConfig->setConfig(['account' => 'test_account']);

        $paymentMethod = new CorePaymentMethod();
        $paymentMethod->setCode('hipay_test');
        $paymentMethod->setGatewayConfig($gatewayConfig);
        $paymentMethod->addChannel($channel);

        $translation = new PaymentMethodTranslation();
        $translation->setLocale('en_US');
        $translation->setName('HiPay Test');
        $paymentMethod->addTranslation($translation);

        $this->entityManager->persist($gatewayConfig);
        $this->entityManager->persist($paymentMethod);
        $this->entityManager->flush();

        return $paymentMethod;
    }

    private function getHiPayStateForStatus(int $status): string
    {
        return match ($status) {
            116, 117, 118, 119 => 'completed',
            124, 125, 126 => 'refunded',
            113, 109, 110, 111 => 'declined',
            115, 143 => 'cancelled',
            default => 'pending',
        };
    }
}
