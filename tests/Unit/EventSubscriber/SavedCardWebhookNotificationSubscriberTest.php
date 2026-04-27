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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Entity\SavedCard;
use HiPay\SyliusHiPayPlugin\Entity\SavedCardInterface;
use HiPay\SyliusHiPayPlugin\Event\AfterWebhookNotificationProcessedEvent;
use HiPay\SyliusHiPayPlugin\EventSubscriber\SavedCardWebhookNotificationSubscriber;
use HiPay\SyliusHiPayPlugin\Factory\SavedCardFactory;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Payment\HiPayStatus;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Repository\SavedCardRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Customer\Model\CustomerInterface;

final class SavedCardWebhookNotificationSubscriberTest extends TestCase
{
    private SavedCardFactory&MockObject $factory;

    private HiPayLoggerInterface&MockObject $logger;

    private AccountProviderInterface&MockObject $accountProvider;

    private EntityManagerInterface&MockObject $entityManager;

    private SavedCardRepositoryInterface&MockObject $savedCardRepository;

    private SavedCardWebhookNotificationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->factory = $this->createMock(SavedCardFactory::class);
        $this->logger = $this->createMock(HiPayLoggerInterface::class);
        $this->accountProvider = $this->createMock(AccountProviderInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->savedCardRepository = $this->createMock(SavedCardRepositoryInterface::class);

        $this->subscriber = new SavedCardWebhookNotificationSubscriber(
            $this->factory,
            $this->logger,
            $this->accountProvider,
            $this->entityManager,
            $this->savedCardRepository,
        );
    }

    public function testCreatesCardWithAuthorizedFalseOnAuthorizedNotification(): void
    {
        $savedCard = $this->createMock(SavedCardInterface::class);
        $savedCard->method('getId')->willReturn(42);

        $this->factory->expects($this->once())->method('createNewFromPayment')->willReturn($savedCard);
        $this->entityManager->expects($this->once())->method('persist')->with($savedCard);
        $this->entityManager->expects($this->once())->method('flush');

        $event = $this->createEvent(HiPayStatus::Authorized->value, ['multi_use' => 1, 'token' => 'tok']);

        $this->subscriber->onAfterWebhookNotificationProcessed($event);
    }

    public function testAuthorizesExistingCardOnCapturedNotification(): void
    {
        $savedCard = new SavedCard();
        $savedCard->setAuthorized(false);
        $savedCard->setToken('tok');
        $savedCard->setBrand('visa');
        $savedCard->setMaskedPan('411111****1111');
        $savedCard->setExpiryMonth('12');
        $savedCard->setExpiryYear('2030');

        $this->savedCardRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($savedCard);

        $this->factory->expects($this->never())->method('createNewFromPayment');
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $event = $this->createEvent(HiPayStatus::Captured->value, ['multi_use' => 1, 'token' => 'tok']);

        $this->subscriber->onAfterWebhookNotificationProcessed($event);

        $this->assertTrue($savedCard->isAuthorized());
    }

    public function testFallbackCreatesAuthorizedCardOnCapturedWhenNoCardExists(): void
    {
        $savedCard = $this->createMock(SavedCardInterface::class);
        $savedCard->method('getId')->willReturn(99);
        $savedCard->expects($this->once())->method('setAuthorized')->with(true);

        $this->savedCardRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->factory->expects($this->once())->method('createNewFromPayment')->willReturn($savedCard);
        $this->entityManager->expects($this->once())->method('persist')->with($savedCard);
        $this->entityManager->expects($this->once())->method('flush');

        $event = $this->createEvent(HiPayStatus::Captured->value, ['multi_use' => 1, 'token' => 'tok']);

        $this->subscriber->onAfterWebhookNotificationProcessed($event);
    }

    public function testSkipsOnAuthorizedWhenMultiUseIsZero(): void
    {
        $this->factory->expects($this->never())->method('createNewFromPayment');

        $event = $this->createEvent(HiPayStatus::Authorized->value, ['multi_use' => 0, 'token' => 'tok']);

        $this->subscriber->onAfterWebhookNotificationProcessed($event);
    }

    public function testSkipsOnCapturedWhenMultiUseIsZero(): void
    {
        $this->factory->expects($this->never())->method('createNewFromPayment');
        $this->savedCardRepository->expects($this->never())->method('findOneBy');

        $event = $this->createEvent(HiPayStatus::Captured->value, ['multi_use' => 0, 'token' => 'tok']);

        $this->subscriber->onAfterWebhookNotificationProcessed($event);
    }

    public function testAuthorizesExistingCardOnPartiallyCapturedNotification(): void
    {
        $savedCard = new SavedCard();
        $savedCard->setAuthorized(false);
        $savedCard->setToken('tok');
        $savedCard->setBrand('visa');
        $savedCard->setMaskedPan('411111****1111');
        $savedCard->setExpiryMonth('12');
        $savedCard->setExpiryYear('2030');

        $this->savedCardRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($savedCard);

        $this->factory->expects($this->never())->method('createNewFromPayment');
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $event = $this->createEvent(HiPayStatus::PartiallyCaptured->value, ['multi_use' => 1, 'token' => 'tok']);

        $this->subscriber->onAfterWebhookNotificationProcessed($event);

        $this->assertTrue($savedCard->isAuthorized());
    }

    public function testSkipsOnUnrelatedStatus(): void
    {
        $this->factory->expects($this->never())->method('createNewFromPayment');

        $payment = $this->createMock(PaymentInterface::class);

        $event = new AfterWebhookNotificationProcessedEvent(
            'evt-1',
            ['status' => HiPayStatus::Refused->value],
            $payment,
        );

        $this->subscriber->onAfterWebhookNotificationProcessed($event);
    }

    public function testLogsWarningOnAuthorizedWhenFactoryReturnsNull(): void
    {
        $this->factory->expects($this->once())->method('createNewFromPayment')->willReturn(null);
        $this->logger->expects($this->once())->method('warning');
        $this->entityManager->expects($this->never())->method('persist');

        $event = $this->createEvent(HiPayStatus::Authorized->value, ['multi_use' => 1, 'token' => 'tok']);

        $this->subscriber->onAfterWebhookNotificationProcessed($event);
    }

    public function testLogsWarningOnCapturedFallbackWhenFactoryReturnsNull(): void
    {
        $this->savedCardRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->factory->expects($this->once())->method('createNewFromPayment')->willReturn(null);
        $this->logger->expects($this->once())->method('warning');
        $this->entityManager->expects($this->never())->method('persist');

        $event = $this->createEvent(HiPayStatus::Captured->value, ['multi_use' => 1, 'token' => 'tok']);

        $this->subscriber->onAfterWebhookNotificationProcessed($event);
    }

    /**
     * @param array<string, mixed> $paymentDetails
     */
    private function createEvent(int $status, array $paymentDetails): AfterWebhookNotificationProcessedEvent
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(1);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCustomer')->willReturn($customer);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn($paymentDetails);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getMethod')->willReturn(null);

        return new AfterWebhookNotificationProcessedEvent('evt-1', ['status' => $status], $payment);
    }
}
