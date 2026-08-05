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
use HiPay\Fullservice\Gateway\Model\HostedPaymentPage;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\SyliusHiPayPlugin\Client\ClientProviderInterface;
use HiPay\SyliusHiPayPlugin\Client\HiPayClientInterface;
use HiPay\SyliusHiPayPlugin\Command\HostedPagePaymentRequest;
use HiPay\SyliusHiPayPlugin\CommandHandler\HostedPagePaymentRequestHandler;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Mailer\FraudSuspicionEmailManagerInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\HostedPaymentPageRequestBuilderInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\HostedPaymentPageResponseMapper;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestBuilderRegistryInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContextFactoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class HostedPagePaymentRequestHandlerTest extends TestCase
{
    private PaymentRequestProviderInterface&MockObject $paymentRequestProvider;

    private StateMachineInterface&MockObject $stateMachine;

    private ClientProviderInterface&MockObject $clientProvider;

    private HiPayClientInterface&MockObject $hiPayClient;

    private HostedPaymentPageRequestBuilderInterface&MockObject $hostedPageRequestBuilder;

    private NormalizerInterface&MockObject $serializer;

    private EntityManagerInterface&MockObject $entityManager;

    private PaymentOrderRequestContextFactoryInterface&MockObject $contextFactory;

    private HiPayLoggerInterface&MockObject $logger;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private HostedPagePaymentRequestHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRequestProvider = $this->createMock(PaymentRequestProviderInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->clientProvider = $this->createMock(ClientProviderInterface::class);
        $this->hiPayClient = $this->createMock(HiPayClientInterface::class);
        $this->hostedPageRequestBuilder = $this->createMock(HostedPaymentPageRequestBuilderInterface::class);
        $this->serializer = $this->createMock(NormalizerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->contextFactory = $this->createMock(PaymentOrderRequestContextFactoryInterface::class);
        $this->logger = $this->createMock(HiPayLoggerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->clientProvider->method('getForAccount')->willReturn($this->hiPayClient);
        $this->stateMachine->method('can')->willReturn(true);

        $this->handler = new HostedPagePaymentRequestHandler(
            $this->paymentRequestProvider,
            $this->stateMachine,
            $this->clientProvider,
            $this->createMock(PaymentOrderRequestBuilderRegistryInterface::class),
            $this->serializer,
            $this->entityManager,
            $this->createMock(FactoryInterface::class),
            $this->contextFactory,
            $this->createMock(RepositoryInterface::class),
            $this->logger,
            $this->createMock(FraudSuspicionEmailManagerInterface::class),
            $this->eventDispatcher,
            $this->hostedPageRequestBuilder,
            new HostedPaymentPageResponseMapper(),
        );
    }

    public function testInvokeCallsHostedPaymentPageApiAndStoresForwardingResponse(): void
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $account = $this->createMock(AccountInterface::class);
        $hostedPageRequest = new HostedPaymentPageRequest();

        $paymentRequest->method('getPayment')->willReturn($payment);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_CAPTURE);
        $paymentRequest->method('getId')->willReturn('1');

        $this->paymentRequestProvider->method('provide')->willReturn($paymentRequest);
        $this->contextFactory->method('buildFromPaymentRequest')->willReturn(new PaymentOrderRequestContext(
            order: $order,
            payment: $payment,
            paymentRequest: $paymentRequest,
            account: $account,
            paymentProduct: 'hosted_page',
            payload: [],
            gatewayConfig: ['payment_products' => ['card']],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        ));

        $this->hostedPageRequestBuilder->method('build')->willReturn($hostedPageRequest);
        $this->serializer->method('normalize')->willReturn(['orderid' => 'ORDER-1']);
        $this->hiPayClient
            ->expects($this->once())
            ->method('requestHostedPaymentPage')
            ->with($hostedPageRequest)
            ->willReturn(new HostedPaymentPage('123', 'https://secure-gateway.hipay-tpp.com/forward/abc', null))
        ;

        $this->entityManager->expects($this->once())->method('flush');

        ($this->handler)(new HostedPagePaymentRequest('hash-123'));
    }

    public function testInvokeUsesHostedPageV2ClientWhenGatewayConfigEnablesIt(): void
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $account = $this->createMock(AccountInterface::class);
        $hostedPageRequest = new HostedPaymentPageRequest();

        $paymentRequest->method('getPayment')->willReturn($payment);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_CAPTURE);
        $paymentRequest->method('getId')->willReturn('1');

        $this->paymentRequestProvider->method('provide')->willReturn($paymentRequest);
        $this->contextFactory->method('buildFromPaymentRequest')->willReturn(new PaymentOrderRequestContext(
            order: $order,
            payment: $payment,
            paymentRequest: $paymentRequest,
            account: $account,
            paymentProduct: 'hosted_page',
            payload: [],
            gatewayConfig: [
                'payment_products' => ['card'],
            ],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        ));

        $this->hostedPageRequestBuilder->method('build')->willReturn($hostedPageRequest);
        $this->serializer->method('normalize')->willReturn(['orderid' => 'ORDER-1']);

        $this->clientProvider
            ->expects($this->once())
            ->method('getForAccount')
            ->with($account, true)
            ->willReturn($this->hiPayClient)
        ;

        $this->hiPayClient
            ->expects($this->once())
            ->method('requestHostedPaymentPage')
            ->with($hostedPageRequest)
            ->willReturn(new HostedPaymentPage('123', 'https://secure-gateway.hipay-tpp.com/forward/abc', null))
        ;

        ($this->handler)(new HostedPagePaymentRequest('hash-123'));
    }
}
