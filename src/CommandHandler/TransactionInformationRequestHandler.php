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
use HiPay\SyliusHiPayPlugin\Client\ClientProviderInterface;
use HiPay\SyliusHiPayPlugin\Command\TransactionInformationRequest;
use HiPay\SyliusHiPayPlugin\Entity\TransactionInterface;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Mailer\FraudSuspicionEmailManagerInterface;
use HiPay\SyliusHiPayPlugin\Payment\PaymentPayloadRedactorInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestBuilderRegistryInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContextFactoryInterface;
use RuntimeException;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class TransactionInformationRequestHandler extends AbstractPaymentRequestHandler
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
        private PaymentPayloadRedactorInterface $payloadRedactor,
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

    public function __invoke(TransactionInformationRequest $transactionInformationRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($transactionInformationRequest);
        $context = $this->paymentOrderRequestContextFactory->buildFromPaymentRequest($paymentRequest);

        $this->logger->setAccount($context->account);

        try {
            $this->setAllProcessState($paymentRequest, $context->payment);

            /** @var ?TransactionInterface $transaction */
            $transaction = $this->transationRepository->findOneBy(['payment' => $context->payment]);
            $reference = $transaction?->getTransactionReference();
            if (null === $reference) {
                // Hosted Page race: webhook not yet processed, transaction not yet created.
                // Return PENDING so the user reaches the thank-you page; the scheduler
                // worker will apply the real state when it processes the notification.
                $paymentRequest->setResponseData(['state' => TransactionState::PENDING]);
                $this->setCompleteState($paymentRequest);
                $this->entityManager->flush();

                return;
            }

            $this->logger->info('[Hipay][TransactionInformationRequestHandler] Before sending request', [
                'request_id' => $paymentRequest->getId(),
                'transaction_reference' => $reference,
            ]);

            $paymentRequest->setPayload(['transaction_reference' => $reference]);
            $transaction = $this->hiPayClientProvider->getForAccount($context->account)->requestTransactionInformation($reference);
            if (null === $transaction) {
                throw new RuntimeException('No transaction found');
            }

            /** @var array<string, mixed> $normalizedTransaction */
            $normalizedTransaction = $this->serializer->normalize($transaction);
            $paymentRequest->setResponseData($normalizedTransaction);

            $this->logger->info('[Hipay][TransactionInformationRequestHandler] After sending request', [
                'request_id' => $paymentRequest->getId(),
                'transaction_reference' => $reference,
                'transaction' => $this->payloadRedactor->redact($normalizedTransaction),
            ]);

            /** @var array<string, mixed> $responseData */
            $responseData = $paymentRequest->getResponseData();
            $this->dispatchAfterPaymentProcessedEvent(
                $context->payment,
                $paymentRequest,
                $responseData,
                $context->action,
            );

            match ($transaction->getState()) {
                TransactionState::DECLINED, TransactionState::ERROR => $this->setAllFailState($paymentRequest, $context->payment),
                default => $this->setCompleteState($paymentRequest),
            };
        } catch (Exception $e) {
            $paymentRequest->setResponseData([
                'error' => $e->getMessage(),
                'state' => TransactionState::ERROR,
            ]);

            $this->logger->error('[Hipay][TransactionInformationRequestHandler] Error during request', [
                'request_id' => $paymentRequest->getId(),
                'error' => $e->getMessage(),
            ]);

            /** @var array<string, mixed> $responseData */
            $responseData = $paymentRequest->getResponseData();
            $this->dispatchAfterPaymentProcessedEvent(
                $context->payment,
                $paymentRequest,
                $responseData,
                $context->action,
            );

            $this->setAllFailState($paymentRequest, $context->payment);
        } finally {
            $this->entityManager->flush();
        }
    }
}
