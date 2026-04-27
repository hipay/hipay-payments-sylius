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

namespace Tests\HiPay\SyliusHiPayPlugin\Integration\Provider;

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Entity\Account;
use HiPay\SyliusHiPayPlugin\Entity\SavedCard;
use HiPay\SyliusHiPayPlugin\Factory\SavedCardFactoryInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig as PayumGatewayConfig;
use Sylius\Component\Core\Model\Channel as CoreChannel;
use Sylius\Component\Core\Model\Customer;
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
 * Integration test: SavedCard from payment details (webhook / post-capture flow).
 */
final class SavedCardFactoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private SavedCardFactoryInterface $savedCardFactory;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get('doctrine.orm.entity_manager');
        $this->savedCardFactory = $container->get(SavedCardFactoryInterface::class);
    }

    public function testCreateNewFromPaymentPersistsCardWhenPaymentDetailsAreComplete(): void
    {
        $order = $this->createOrderWithCustomerAndPayment();
        $payment = $order->getLastPayment();
        self::assertInstanceOf(PaymentInterface::class, $payment);

        $payment->setDetails([
            'pan' => '411111****1111',
            'token' => 'hipay-token-save-test',
            'brand' => 'VISA',
            'card_expiry_month' => '12',
            'card_expiry_year' => '2030',
            'card_holder' => 'JOHN DOE',
        ]);
        $this->entityManager->flush();

        $savedCard = $this->savedCardFactory->createNewFromPayment($payment);

        self::assertNotNull($savedCard);

        $customerId = $order->getCustomer()?->getId();
        self::assertNotNull($customerId);

        $this->entityManager->clear();

        self::assertInstanceOf(SavedCard::class, $savedCard);
        self::assertSame('hipay-token-save-test', $savedCard->getToken());
        self::assertSame('411111****1111', $savedCard->getMaskedPan());
        self::assertSame('visa', $savedCard->getBrand());
        self::assertSame('12', $savedCard->getExpiryMonth());
        self::assertSame('2030', $savedCard->getExpiryYear());
        self::assertSame('JOHN DOE', $savedCard->getHolder());
        self::assertFalse($savedCard->isAuthorized());
        self::assertSame($customerId, $savedCard->getCustomer()?->getId());
    }

    public function testCreateNewFromPaymentReturnsNullWhenCardHolderMissing(): void
    {
        $order = $this->createOrderWithCustomerAndPayment();
        $payment = $order->getLastPayment();
        self::assertInstanceOf(PaymentInterface::class, $payment);

        $payment->setDetails([
            'pan' => '411111****1111',
            'token' => 'hipay-token-no-holder',
            'brand' => 'VISA',
            'card_expiry_month' => '12',
            'card_expiry_year' => '2030',
        ]);
        $this->entityManager->flush();

        $savedCard = $this->savedCardFactory->createNewFromPayment($payment);

        self::assertNull($savedCard);
    }

    private function createOrderWithCustomerAndPayment(): OrderInterface
    {
        $this->getOrCreateAccount('test_account_saved_card');
        $channel = $this->getOrCreateChannel();
        $paymentMethod = $this->getOrCreatePaymentMethod($channel);

        $orderFactory = self::getContainer()->get('sylius.factory.order');
        $paymentFactory = self::getContainer()->get('sylius.factory.payment');
        $customerFactory = self::getContainer()->get('sylius.factory.customer');

        /** @var Customer $customer */
        $customer = $customerFactory->createNew();
        $customer->setEmail('saved-card-' . uniqid('', true) . '@example.com');
        $customer->setFirstName('Jane');
        $customer->setLastName('Doe');
        $this->entityManager->persist($customer);

        /** @var OrderInterface $order */
        $order = $orderFactory->createNew();
        $order->setChannel($channel);
        $order->setLocaleCode('en_US');
        $order->setCurrencyCode('EUR');
        $order->setTokenValue('ORDER-SAVED-CARD-' . uniqid('', true));
        $order->setCustomer($customer);

        /** @var PaymentInterface $payment */
        $payment = $paymentFactory->createNew();
        $payment->setOrder($order);
        $payment->setMethod($paymentMethod);
        $payment->setAmount(1500);
        $payment->setCurrencyCode('EUR');
        $payment->setState(BasePaymentInterface::STATE_NEW);

        $order->addPayment($payment);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

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
        $account->setName('Test Account Saved Card');
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

    private function getOrCreateChannel(): CoreChannel
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

    private function getOrCreatePaymentMethod(CoreChannel $channel): PaymentMethodInterface
    {
        $pmRepo = $this->entityManager->getRepository(CorePaymentMethod::class);
        $paymentMethod = $pmRepo->findOneBy(['code' => 'hipay_saved_card_test']);

        if (null !== $paymentMethod) {
            $paymentMethod->setCurrentLocale('en_US');

            return $paymentMethod;
        }

        $gatewayConfig = new PayumGatewayConfig();
        $gatewayConfig->setGatewayName('hipay_hosted_fields');
        $gatewayConfig->setFactoryName('hipay_hosted_fields');
        $gatewayConfig->setConfig(['account' => 'test_account_saved_card']);

        $paymentMethod = new CorePaymentMethod();
        $paymentMethod->setCode('hipay_saved_card_test');
        $paymentMethod->setGatewayConfig($gatewayConfig);
        $paymentMethod->addChannel($channel);

        $translation = new PaymentMethodTranslation();
        $translation->setLocale('en_US');
        $translation->setName('HiPay Saved Card Test');
        $paymentMethod->addTranslation($translation);

        $this->entityManager->persist($gatewayConfig);
        $this->entityManager->persist($paymentMethod);
        $this->entityManager->flush();

        return $paymentMethod;
    }
}
