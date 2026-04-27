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

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Event\AfterWebhookNotificationProcessedEvent;
use HiPay\SyliusHiPayPlugin\EventSubscriber\FraudSuspicionWebhookNotificationSubscriber;
use HiPay\SyliusHiPayPlugin\Mailer\FraudSuspicionEmailManagerInterface;
use HiPay\SyliusHiPayPlugin\Payment\HiPayStatus;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;

final class FraudSuspicionWebhookNotificationSubscriberTest extends TestCase
{
    private FraudSuspicionEmailManagerInterface&MockObject $emailManager;

    private AccountProviderInterface&MockObject $accountProvider;

    private FraudSuspicionWebhookNotificationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->emailManager = $this->createMock(FraudSuspicionEmailManagerInterface::class);
        $this->accountProvider = $this->createMock(AccountProviderInterface::class);

        $this->subscriber = new FraudSuspicionWebhookNotificationSubscriber(
            $this->emailManager,
            $this->accountProvider,
        );
    }

    public function testSendsEmailOnStatus112(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $account = $this->createMock(AccountInterface::class);

        $this->accountProvider->expects($this->once())
            ->method('getByPayment')
            ->with($payment)
            ->willReturn($account);

        $this->emailManager->expects($this->once())
            ->method('sendFraudSuspicionEmail')
            ->with($account, $payment);

        $event = new AfterWebhookNotificationProcessedEvent(
            'evt-1',
            ['status' => HiPayStatus::AuthorizedAndPending->value],
            $payment,
        );

        $this->subscriber->onAfterWebhookNotificationProcessed($event);
    }

    public function testSkipsEmailOnNonFraudStatus(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->accountProvider->expects($this->never())->method('getByPayment');
        $this->emailManager->expects($this->never())->method('sendFraudSuspicionEmail');

        $event = new AfterWebhookNotificationProcessedEvent(
            'evt-2',
            ['status' => HiPayStatus::Captured->value],
            $payment,
        );

        $this->subscriber->onAfterWebhookNotificationProcessed($event);
    }

    public function testSkipsEmailWhenNoAccountFound(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->accountProvider->expects($this->once())
            ->method('getByPayment')
            ->with($payment)
            ->willReturn(null);

        $this->emailManager->expects($this->never())->method('sendFraudSuspicionEmail');

        $event = new AfterWebhookNotificationProcessedEvent(
            'evt-3',
            ['status' => HiPayStatus::AuthorizedAndPending->value],
            $payment,
        );

        $this->subscriber->onAfterWebhookNotificationProcessed($event);
    }
}
