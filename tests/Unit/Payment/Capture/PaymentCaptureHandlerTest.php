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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Payment\Capture;

use Doctrine\ORM\EntityManagerInterface;
use HiPay\Fullservice\Gateway\Request\Maintenance\MaintenanceRequest;
use HiPay\SyliusHiPayPlugin\Client\ClientProviderInterface;
use HiPay\SyliusHiPayPlugin\Client\HiPayClient;
use HiPay\SyliusHiPayPlugin\Client\HiPayClientInterface;
use HiPay\SyliusHiPayPlugin\Entity\TransactionInterface;
use HiPay\SyliusHiPayPlugin\Payment\Capture\PaymentCaptureEligibilityCheckerInterface;
use HiPay\SyliusHiPayPlugin\Payment\Capture\PaymentCaptureHandler;
use HiPay\SyliusHiPayPlugin\Payment\Capture\Request\CaptureRequestBuilderInterface;
use HiPay\SyliusHiPayPlugin\Payment\OrderAdvisoryLockInterface;
use HiPay\SyliusHiPayPlugin\Provider\PaymentRequestProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\TransactionProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final class PaymentCaptureHandlerTest extends TestCase
{
    private PaymentCaptureEligibilityCheckerInterface&MockObject $eligibilityChecker;

    private ClientProviderInterface&MockObject $clientProvider;

    private PaymentRequestProviderInterface&MockObject $paymentRequestProvider;

    private TransactionProviderInterface&MockObject $transactionProvider;

    private CaptureRequestBuilderInterface&MockObject $captureRequestBuilder;

    private OrderAdvisoryLockInterface&MockObject $orderAdvisoryLock;

    private EntityManagerInterface&MockObject $entityManager;

    private HiPayClientInterface&MockObject $client;

    private MaintenanceRequest $captureRequest;

    private PaymentCaptureHandler $handler;

    protected function setUp(): void
    {
        $this->eligibilityChecker = $this->createMock(PaymentCaptureEligibilityCheckerInterface::class);
        $this->clientProvider = $this->createMock(ClientProviderInterface::class);
        $this->paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $this->transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $this->captureRequestBuilder = $this->createMock(CaptureRequestBuilderInterface::class);
        $this->orderAdvisoryLock = $this->createMock(OrderAdvisoryLockInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->client = $this->createMock(HiPayClientInterface::class);

        $this->captureRequest = new MaintenanceRequest();
        $this->captureRequest->operation = 'capture';
        $this->captureRequest->operation_id = 'capture-000000018-17';
        $this->captureRequest->amount = 100.0;
        $this->captureRequestBuilder->method('build')->willReturn($this->captureRequest);

        $this->handler = new PaymentCaptureHandler(
            $this->eligibilityChecker,
            $this->clientProvider,
            $this->paymentRequestProvider,
            $this->transactionProvider,
            $this->captureRequestBuilder,
            $this->orderAdvisoryLock,
            $this->entityManager,
        );
    }

    public function testItReturnsFailureAndDoesNotCallTheApiWhenPaymentIsNotEligible(): void
    {
        $this->orderAdvisoryLock->method('acquire')->willReturn('hipay_order_lock');
        $this->eligibilityChecker->method('isEligible')->willReturn(false);
        $this->clientProvider->expects($this->never())->method('getForPaymentMethod');

        $result = $this->handler->capture($this->createPayment());

        self::assertFalse($result->isSuccessful());
        self::assertStringContainsString('not eligible for capture', (string) $result->getError());
    }

    public function testItAbortsWhenTheAdvisoryLockCannotBeAcquiredOnASupportedPlatform(): void
    {
        $this->orderAdvisoryLock->method('acquire')->willReturn(null);

        $this->eligibilityChecker->expects($this->never())->method('isEligible');
        $this->clientProvider->expects($this->never())->method('getForPaymentMethod');
        $this->paymentRequestProvider->expects($this->never())->method('createPaymentRequest');

        $result = $this->handler->capture($this->createPayment());

        self::assertFalse($result->isSuccessful());
        self::assertStringContainsString('already in progress', (string) $result->getError());
    }

    public function testItSendsTheBuiltCaptureRequestAndLogsItWithoutTransitioningThePayment(): void
    {
        $payment = $this->createPayment();
        $this->makeEligibleWithTransactionReference('txn_ref_123');

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $this->paymentRequestProvider
            ->expects($this->once())
            ->method('createPaymentRequest')
            ->with(
                $payment,
                $this->isInstanceOf(PaymentMethodInterface::class),
                'capture_request',
                HiPayClient::serializeRequest($this->captureRequest),
            )
            ->willReturn($paymentRequest);

        $this->clientProvider->method('getForPaymentMethod')->willReturn($this->client);
        $this->client
            ->expects($this->once())
            ->method('capturePayment')
            ->with('txn_ref_123', $this->captureRequest)
            ->willReturn(['state' => 'captured']);

        // The request payload is logged at creation (createPaymentRequest above), so the
        // handler only records HiPay's response here.
        $paymentRequest->expects($this->never())->method('setPayload');
        $paymentRequest->expects($this->once())->method('setResponseData')->with(['state' => 'captured']);
        $this->paymentRequestProvider->expects($this->once())->method('setCompleteState')->with($paymentRequest);
        $this->paymentRequestProvider->expects($this->never())->method('setFailState');
        // The payment itself must NOT be transitioned by the BO action.
        $payment->expects($this->never())->method('setState');

        $result = $this->handler->capture($payment);

        self::assertTrue($result->isSuccessful());
        self::assertNull($result->getError());
    }

    public function testItFailsThePaymentRequestAndReturnsFailureWhenTheApiCallFails(): void
    {
        $payment = $this->createPayment();
        $this->makeEligibleWithTransactionReference('txn_ref_123');

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $this->paymentRequestProvider->method('createPaymentRequest')->willReturn($paymentRequest);

        $this->clientProvider->method('getForPaymentMethod')->willReturn($this->client);
        $this->client->method('capturePayment')->willThrowException(new RuntimeException('HiPay down'));

        $this->paymentRequestProvider->expects($this->once())->method('setFailState')->with($paymentRequest);
        $this->paymentRequestProvider->expects($this->never())->method('setCompleteState');

        $result = $this->handler->capture($payment);

        self::assertFalse($result->isSuccessful());
        self::assertStringContainsString('HiPay down', (string) $result->getError());
    }

    private function makeEligibleWithTransactionReference(string $reference): void
    {
        $this->orderAdvisoryLock->method('acquire')->willReturn('hipay_order_lock');
        $this->eligibilityChecker->method('isEligible')->willReturn(true);

        $transaction = $this->createMock(TransactionInterface::class);
        $transaction->method('getTransactionReference')->willReturn($reference);
        $this->transactionProvider->method('getByPayment')->willReturn($transaction);
    }

    private function createPayment(): PaymentInterface&MockObject
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getId')->willReturn(17);
        $payment->method('getMethod')->willReturn($this->createMock(PaymentMethodInterface::class));
        $payment->method('getOrder')->willReturn($this->createMock(OrderInterface::class));
        $payment->method('getDetails')->willReturn([]);

        return $payment;
    }
}
