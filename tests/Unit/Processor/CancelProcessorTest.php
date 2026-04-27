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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Processor;

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Client\ClientProviderInterface;
use HiPay\SyliusHiPayPlugin\Client\HiPayClientInterface;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Entity\TransactionInterface;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Processor\CancelProcessor;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\PaymentRequestProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\TransactionProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Resource\Exception\UpdateHandlingException;

final class CancelProcessorTest extends TestCase
{
    private ClientProviderInterface $clientProvider;

    private TransactionProviderInterface $transactionProvider;

    private AccountProviderInterface $accountProvider;

    private HiPayLoggerInterface $logger;

    private EntityManagerInterface $entityManager;

    private CancelProcessor $processor;

    private PaymentRequestProviderInterface $paymentRequestProvider;

    protected function setUp(): void
    {
        $this->clientProvider = $this->createMock(ClientProviderInterface::class);
        $this->transactionProvider = $this->createMock(TransactionProviderInterface::class);
        $this->accountProvider = $this->createMock(AccountProviderInterface::class);
        $this->logger = $this->createMock(HiPayLoggerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);

        $this->processor = new CancelProcessor(
            $this->clientProvider,
            $this->transactionProvider,
            $this->accountProvider,
            $this->logger,
            $this->entityManager,
            $this->paymentRequestProvider,
        );
    }

    public function testCancelSuccess(): void
    {
        $transactionRef = 'TXN-123';
        $cancelResponse = ['state' => 'cancelled'];

        $payment = $this->createHiPayPayment(PaymentInterface::STATE_AUTHORIZED, 'hipay_hosted_fields');
        $transaction = $this->createTransaction($transactionRef);
        $account = $this->createAccount('test_account');
        $hipayClient = $this->createMock(HiPayClientInterface::class);

        $this->transactionProvider->method('getByPayment')->with($payment)->willReturn($transaction);
        $this->accountProvider->method('getByPaymentMethod')->willReturn($account);
        $this->clientProvider->method('getForAccountCode')->with('test_account')->willReturn($hipayClient);
        $hipayClient->method('cancelPayment')->with($transactionRef)->willReturn($cancelResponse);

        $payment->expects(self::once())->method('getDetails')->willReturn([]);
        $payment->expects(self::once())->method('setDetails')->with(['cancel_response' => $cancelResponse]);

        $this->processor->cancel($payment);
    }

    public function testCancelReturnsEarlyWhenNonHiPayGateway(): void
    {
        $payment = $this->createHiPayPayment(PaymentInterface::STATE_AUTHORIZED, 'stripe');

        $this->transactionProvider->expects(self::never())->method('getByPayment');
        $this->accountProvider->expects(self::never())->method('getByPaymentMethod');
        $this->clientProvider->expects(self::never())->method('getForAccountCode');

        $this->processor->cancel($payment);
    }

    public function testCancelThrowsWhenPaymentNotAuthorized(): void
    {
        $this->expectException(UpdateHandlingException::class);

        $payment = $this->createHiPayPayment(PaymentInterface::STATE_COMPLETED, 'hipay_hosted_fields');
        $payment->method('getId')->willReturn(42);

        $this->logger->expects(self::once())->method('warning')->with(
            'Attempt to cancel an unauthorized payment.',
            ['payment_id' => 42],
        );

        $this->processor->cancel($payment);
    }

    public function testCancelThrowsWhenTransactionReferenceNotFound(): void
    {
        $this->expectException(UpdateHandlingException::class);

        $payment = $this->createHiPayPayment(PaymentInterface::STATE_AUTHORIZED, 'hipay_hosted_fields');
        $payment->method('getId')->willReturn(42);

        $this->transactionProvider->method('getByPayment')->with($payment)->willReturn(null);

        $this->logger->expects(self::once())->method('warning')->with(
            'HiPay transaction reference not found for this payment.',
            ['payment_id' => 42],
        );

        $this->processor->cancel($payment);
    }

    public function testCancelThrowsWhenAccountNotFound(): void
    {
        $this->expectException(UpdateHandlingException::class);

        $payment = $this->createHiPayPayment(PaymentInterface::STATE_AUTHORIZED, 'hipay_hosted_fields');
        $payment->method('getId')->willReturn(42);

        $this->transactionProvider->method('getByPayment')->with($payment)->willReturn($this->createTransaction('TXN-123'));
        $this->accountProvider->method('getByPaymentMethod')->willReturn(null);

        $this->logger->expects(self::once())->method('warning')->with(
            'HiPay account not found for this payment.',
            ['payment_id' => 42],
        );

        $this->processor->cancel($payment);
    }

    public function testCancelThrowsWhenClientThrowsRuntimeException(): void
    {
        $this->expectException(UpdateHandlingException::class);

        $payment = $this->createHiPayPayment(PaymentInterface::STATE_AUTHORIZED, 'hipay_hosted_fields');
        $payment->method('getId')->willReturn(42);

        $this->transactionProvider->method('getByPayment')->with($payment)->willReturn($this->createTransaction('TXN-123'));
        $this->accountProvider->method('getByPaymentMethod')->willReturn($this->createAccount('test'));
        $this->clientProvider->method('getForAccountCode')->willReturn($hipayClient = $this->createMock(HiPayClientInterface::class));
        $hipayClient->method('cancelPayment')->with('TXN-123')->willThrowException(new RuntimeException('HiPay API error'));

        $this->logger->expects(self::once())->method('error')->with(
            self::stringContains('The cancellation failed on HiPay'),
            self::anything(),
        );

        $this->processor->cancel($payment);
    }

    /**
     * @param array<string, mixed> $details
     */
    private function createHiPayPayment(string $state, string $factoryName, array $details = []): PaymentInterface|MockObject
    {
        $gatewayConfig = $this->createMock(GatewayConfig::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getState')->willReturn($state);
        $payment->method('getDetails')->willReturn($details);

        return $payment;
    }

    private function createTransaction(string $transactionReference): TransactionInterface|MockObject
    {
        $transaction = $this->createMock(TransactionInterface::class);
        $transaction->method('getTransactionReference')->willReturn($transactionReference);

        return $transaction;
    }

    private function createAccount(string $code): AccountInterface|MockObject
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('getCode')->willReturn($code);

        return $account;
    }
}
