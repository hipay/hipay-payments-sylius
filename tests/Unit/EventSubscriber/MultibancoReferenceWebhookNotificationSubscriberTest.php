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
use HiPay\SyliusHiPayPlugin\EventSubscriber\MultibancoReferenceWebhookNotificationSubscriber;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Mailer\MultibancoReferenceEmailManagerInterface;
use HiPay\SyliusHiPayPlugin\Payment\HiPayStatus;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PluginPaymentProduct;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\GatewayProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;

final class MultibancoReferenceWebhookNotificationSubscriberTest extends TestCase
{
    private MultibancoReferenceEmailManagerInterface&MockObject $emailManager;

    private AccountProviderInterface&MockObject $accountProvider;

    private MultibancoReferenceWebhookNotificationSubscriber $subscriber;

    private HiPayLoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->emailManager = $this->createMock(MultibancoReferenceEmailManagerInterface::class);
        $this->accountProvider = $this->createMock(AccountProviderInterface::class);
        $this->logger = $this->createMock(HiPayLoggerInterface::class);

        $this->subscriber = new MultibancoReferenceWebhookNotificationSubscriber(
            $this->emailManager,
            $this->accountProvider,
            $this->logger,
        );
    }

    public function testSendsEmailOnStatus142WithMultibancoHostedPage(): void
    {
        $payment = $this->createPaymentWithFactoryName(GatewayProvider::FACTORY_HOSTED_PAGE);
        $account = $this->createMock(AccountInterface::class);

        $this->accountProvider->expects($this->once())
            ->method('getByPayment')
            ->with($payment)
            ->willReturn($account);

        $this->emailManager->expects($this->once())
            ->method('sendMultibancoReferenceEmail')
            ->with($account, $payment, [
                'reference' => '261860464',
                'amount' => 91.97,
                'entity' => '48257',
                'expirationDate' => '2026-07-04',
                'expirationDatetime' => '2026-07-04T23:59:59+01:00',
            ]);

        $event = new AfterWebhookNotificationProcessedEvent(
            'evt-1',
            [
                'status' => HiPayStatus::AuthorizationRequested->value,
                'payment_product' => PluginPaymentProduct::MULTIBANCO->value,
                'payment_reference' => [
                    'reference' => '261860464',
                    'amount' => '91.97',
                    'entity' => '48257',
                    'expiration_date' => '2026-07-04',
                    'expiration_datetime' => '2026-07-04T23:59:59+01:00',
                ],
            ],
            $payment,
        );

        $this->subscriber->onAfterWebhookNotificationProcessed($event);
    }

    public function testSkipsEmailOnNonAuthorizationRequestedStatus(): void
    {
        $payment = $this->createPaymentWithFactoryName(GatewayProvider::FACTORY_HOSTED_PAGE);

        $this->accountProvider->expects($this->never())->method('getByPayment');
        $this->emailManager->expects($this->never())->method('sendMultibancoReferenceEmail');

        $event = new AfterWebhookNotificationProcessedEvent(
            'evt-2',
            [
                'status' => HiPayStatus::Captured->value,
                'payment_product' => PluginPaymentProduct::MULTIBANCO->value,
            ],
            $payment,
        );

        $this->subscriber->onAfterWebhookNotificationProcessed($event);
    }

    public function testSkipsEmailWhenPaymentProductIsNotMultibanco(): void
    {
        $payment = $this->createPaymentWithFactoryName(GatewayProvider::FACTORY_HOSTED_PAGE);

        $this->accountProvider->expects($this->never())->method('getByPayment');
        $this->emailManager->expects($this->never())->method('sendMultibancoReferenceEmail');

        $event = new AfterWebhookNotificationProcessedEvent(
            'evt-3',
            [
                'status' => HiPayStatus::AuthorizationRequested->value,
                'payment_product' => 'visa',
            ],
            $payment,
        );

        $this->subscriber->onAfterWebhookNotificationProcessed($event);
    }

    public function testSkipsEmailWhenPaymentMethodIsNotHostedPage(): void
    {
        $payment = $this->createPaymentWithFactoryName('hipay_hosted_fields');

        $this->accountProvider->expects($this->never())->method('getByPayment');
        $this->emailManager->expects($this->never())->method('sendMultibancoReferenceEmail');

        $event = new AfterWebhookNotificationProcessedEvent(
            'evt-4',
            [
                'status' => HiPayStatus::AuthorizationRequested->value,
                'payment_product' => PluginPaymentProduct::MULTIBANCO->value,
            ],
            $payment,
        );

        $this->subscriber->onAfterWebhookNotificationProcessed($event);
    }

    public function testSkipsEmailWhenNoAccountFound(): void
    {
        $payment = $this->createPaymentWithFactoryName(GatewayProvider::FACTORY_HOSTED_PAGE);

        $this->accountProvider->expects($this->once())
            ->method('getByPayment')
            ->with($payment)
            ->willReturn(null);

        $this->emailManager->expects($this->never())->method('sendMultibancoReferenceEmail');

        $event = new AfterWebhookNotificationProcessedEvent(
            'evt-5',
            [
                'status' => HiPayStatus::AuthorizationRequested->value,
                'payment_product' => PluginPaymentProduct::MULTIBANCO->value,
            ],
            $payment,
        );

        $this->subscriber->onAfterWebhookNotificationProcessed($event);
    }

    private function createPaymentWithFactoryName(string $factoryName): PaymentInterface&MockObject
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);

        return $payment;
    }
}
