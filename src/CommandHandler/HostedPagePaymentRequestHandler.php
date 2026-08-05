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

namespace HiPay\SyliusHiPayPlugin\CommandHandler;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use HiPay\Fullservice\Enum\Transaction\TransactionState;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\SyliusHiPayPlugin\Client\ClientProviderInterface;
use HiPay\SyliusHiPayPlugin\Command\HostedPagePaymentRequest;
use HiPay\SyliusHiPayPlugin\Entity\TransactionInterface;
use HiPay\SyliusHiPayPlugin\Event\BeforeOrderRequestEvent;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Mailer\FraudSuspicionEmailManagerInterface;
use HiPay\SyliusHiPayPlugin\Payment\PaymentRequestAction;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\HostedPaymentPageRequestBuilderInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\HostedPaymentPageResponseMapper;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestBuilderRegistryInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContextFactoryInterface;
use LogicException;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Creates a HiPay Hosted Payment Page and stores the forward URL for redirect.
 */
final readonly class HostedPagePaymentRequestHandler extends AbstractPaymentRequestHandler
{
    /**
     * @param FactoryInterface<TransactionInterface> $transactionFactory
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        PaymentRequestProviderInterface $paymentRequestProvider,
        StateMachineInterface $stateMachine,
        ClientProviderInterface $hiPayClientProvider,
        PaymentOrderRequestBuilderRegistryInterface $builderRegistry,
        NormalizerInterface $serializer,
        EntityManagerInterface $entityManager,
        FactoryInterface $transactionFactory,
        PaymentOrderRequestContextFactoryInterface $paymentOrderRequestContextFactory,
        RepositoryInterface $transationRepository,
        HiPayLoggerInterface $logger,
        FraudSuspicionEmailManagerInterface $fraudSuspicionEmailManager,
        EventDispatcherInterface $eventDispatcher,
        private HostedPaymentPageRequestBuilderInterface $hostedPageRequestBuilder,
        private HostedPaymentPageResponseMapper $hostedPageResponseMapper,
    ) {
        parent::__construct(
            $paymentRequestProvider,
            $stateMachine,
            $hiPayClientProvider,
            $builderRegistry,
            $serializer,
            $entityManager,
            $transactionFactory,
            $paymentOrderRequestContextFactory,
            $transationRepository,
            $logger,
            $fraudSuspicionEmailManager,
            $eventDispatcher,
        );
    }

    public function __invoke(HostedPagePaymentRequest $hostedPagePaymentRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($hostedPagePaymentRequest);
        $context = $this->paymentOrderRequestContextFactory->buildFromPaymentRequest($paymentRequest);
        $action = match ($context->action) {
            PaymentRequestInterface::ACTION_AUTHORIZE => PaymentRequestAction::AUTHORIZE_REQUEST->value,
            default => PaymentRequestAction::CAPTURE_REQUEST->value,
        };

        $this->logger->setAccount($context->account);

        try {
            $this->setAllProcessState($paymentRequest, $context->payment);

            $hostedPageRequest = $this->hostedPageRequestBuilder->build($context);
            $beforeOrderEvent = new BeforeOrderRequestEvent($hostedPageRequest, $context->payment, $paymentRequest, $action);
            $this->eventDispatcher->dispatch($beforeOrderEvent);
            /** @var HostedPaymentPageRequest $hostedPageRequest */
            $hostedPageRequest = $beforeOrderEvent->getOrderRequest();

            $normalizedRequest = $this->serializer->normalize($hostedPageRequest);
            $paymentRequest->setPayload($normalizedRequest);
            $paymentRequest->setAction($action);

            if ($beforeOrderEvent->isApiCallSkipped()) {
                $alternative = $beforeOrderEvent->getAlternativeResponseData();
                if (null === $alternative) {
                    throw new LogicException('BeforeOrderRequestEvent skipped the API call but alternative response data is null.');
                }
                $paymentRequest->setResponseData($alternative);
                $this->dispatchAfterPaymentProcessedEvent($context->payment, $paymentRequest, $paymentRequest->getResponseData(), $action);
                $this->applyPaymentOutcome($paymentRequest, $context->payment);

                return;
            }

            $this->logger->info('[Hipay][HostedPagePaymentRequestHandler] Before sending request', [
                'request_id' => $paymentRequest->getId(),
                'action' => $action,
                'hosted_page_request' => $normalizedRequest,
            ]);

            $hostedPaymentPage = $this->hiPayClientProvider
                ->getForAccount($context->account, true)
                ->requestHostedPaymentPage($hostedPageRequest)
            ;

            $responseData = $this->hostedPageResponseMapper->map($hostedPaymentPage);
            $paymentRequest->setResponseData($responseData);

            $this->logger->info('[Hipay][HostedPagePaymentRequestHandler] After sending request', [
                'request_id' => $paymentRequest->getId(),
                'action' => $action,
                'response' => $responseData,
            ]);

            $this->dispatchAfterPaymentProcessedEvent($context->payment, $paymentRequest, $paymentRequest->getResponseData(), $action);
            $this->applyPaymentOutcome($paymentRequest, $context->payment);
        } catch (Exception $e) {
            $paymentRequest->setResponseData([
                'error' => $e->getMessage(),
                'state' => TransactionState::ERROR,
            ]);

            $this->logger->error('[Hipay][HostedPagePaymentRequestHandler] Error during request', [
                'request_id' => $paymentRequest->getId(),
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            $this->dispatchAfterPaymentProcessedEvent($context->payment, $paymentRequest, $paymentRequest->getResponseData(), $action);
            $this->setAllFailState($paymentRequest, $context->payment);
        } finally {
            $this->entityManager->flush();
        }
    }

    private function applyPaymentOutcome(PaymentRequestInterface $paymentRequest, PaymentInterface $payment): void
    {
        $data = $paymentRequest->getResponseData();
        if (!is_array($data)) {
            $this->setAllFailState($paymentRequest, $payment);

            return;
        }

        $state = $data['state'] ?? TransactionState::ERROR;
        if (TransactionState::FORWARDING === $state) {
            $this->setCompleteState($paymentRequest);

            return;
        }

        if (in_array($state, [TransactionState::DECLINED, TransactionState::ERROR], true)) {
            $this->setAllFailState($paymentRequest, $payment);

            return;
        }

        $this->setCompleteState($paymentRequest);
    }
}
