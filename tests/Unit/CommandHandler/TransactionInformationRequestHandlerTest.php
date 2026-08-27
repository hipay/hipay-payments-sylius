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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\CommandHandler;

use Doctrine\ORM\EntityManagerInterface;
use HiPay\Fullservice\Enum\Transaction\TransactionState;
use HiPay\SyliusHiPayPlugin\Client\ClientProviderInterface;
use HiPay\SyliusHiPayPlugin\Command\TransactionInformationRequest;
use HiPay\SyliusHiPayPlugin\CommandHandler\TransactionInformationRequestHandler;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Mailer\FraudSuspicionEmailManagerInterface;
use HiPay\SyliusHiPayPlugin\Payment\PaymentPayloadRedactor;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestBuilderRegistryInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContextFactoryInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class TransactionInformationRequestHandlerTest extends TestCase
{
    public function testReturnsPendingWhenNoTransactionExistsForPayment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $account = $this->createMock(AccountInterface::class);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getPayment')->willReturn($payment);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_CAPTURE);
        $paymentRequest->method('getId')->willReturn('req-1');

        // Track setResponseData calls to verify PENDING is passed first
        $responseDataCalls = [];
        $paymentRequest->method('setResponseData')
            ->willReturnCallback(static function (array $data) use (&$responseDataCalls): void {
                $responseDataCalls[] = $data;
            });

        $paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $paymentRequestProvider->method('provide')->willReturn($paymentRequest);

        $contextFactory = $this->createMock(PaymentOrderRequestContextFactoryInterface::class);
        $contextFactory->method('buildFromPaymentRequest')->willReturn(new PaymentOrderRequestContext(
            order: $order,
            payment: $payment,
            paymentRequest: $paymentRequest,
            account: $account,
            paymentProduct: 'card',
            payload: [],
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        ));

        // No transaction found for this payment
        $transationRepository = $this->createMock(RepositoryInterface::class);
        $transationRepository->method('findOneBy')->with(['payment' => $payment])->willReturn(null);

        // State machine: can() returns true, track apply() calls
        $stateMachine = $this->createMock(StateMachineInterface::class);
        $stateMachine->method('can')->willReturn(true);
        $applyCalls = [];
        $stateMachine->method('apply')
            ->willReturnCallback(static function () use (&$applyCalls): void {
                $applyCalls[] = func_get_args();
            });

        $flushCount = 0;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('flush')->willReturnCallback(static function () use (&$flushCount): void {
            ++$flushCount;
        });

        $handler = new TransactionInformationRequestHandler(
            $paymentRequestProvider,
            $stateMachine,
            $this->createMock(ClientProviderInterface::class),
            $this->createMock(PaymentOrderRequestBuilderRegistryInterface::class),
            $this->createMock(NormalizerInterface::class),
            $entityManager,
            $this->createMock(FactoryInterface::class),
            $contextFactory,
            $transationRepository,
            $this->createMock(HiPayLoggerInterface::class),
            $this->createMock(FraudSuspicionEmailManagerInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            new PaymentPayloadRedactor(),
        );

        $command = new TransactionInformationRequest('hash-test');
        $handler($command);

        $this->assertGreaterThanOrEqual(1, $flushCount, 'Expected flush() to be called at least once');
        // First setResponseData call must be the PENDING state
        $this->assertSame(['state' => TransactionState::PENDING], $responseDataCalls[0] ?? null);
        // setCompleteState must have been called (apply with TRANSITION_COMPLETE)
        $completeApplied = array_filter(
            $applyCalls,
            static fn (array $args) => ($args[2] ?? null) === PaymentRequestTransitions::TRANSITION_COMPLETE,
        );
        $this->assertNotEmpty($completeApplied, 'Expected TRANSITION_COMPLETE to be applied');
    }
}
