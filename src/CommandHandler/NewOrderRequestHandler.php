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

use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use HiPay\Fullservice\Enum\Transaction\TransactionState;
use HiPay\Fullservice\Gateway\Model\Transaction;
use HiPay\SyliusHiPayPlugin\Client\ClientProviderInterface;
use HiPay\SyliusHiPayPlugin\Command\NewOrderRequest;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Entity\TransactionInterface;
use HiPay\SyliusHiPayPlugin\Event\BeforeOrderRequestEvent;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Mailer\FraudSuspicionEmailManagerInterface;
use HiPay\SyliusHiPayPlugin\Payment\PaymentPayloadRedactorInterface;
use HiPay\SyliusHiPayPlugin\Payment\PaymentRequestAction;
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

readonly class NewOrderRequestHandler extends AbstractPaymentRequestHandler
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

    public function __invoke(NewOrderRequest $newOrderRequest): void
    {
        $paymentRequest = $this->paymentRequestProvider->provide($newOrderRequest);
        $context = $this->paymentOrderRequestContextFactory->buildFromPaymentRequest($paymentRequest);
        $action = match ($context->action) {
            PaymentRequestInterface::ACTION_AUTHORIZE => PaymentRequestAction::AUTHORIZE_REQUEST->value,
            default => PaymentRequestAction::CAPTURE_REQUEST->value,
        };

        $this->logger->setAccount($context->account);

        try {
            $this->setAllProcessState($paymentRequest, $context->payment);

            $orderRequest = $this->builderRegistry->get($context->paymentProduct)->build($context);
            $beforeOrderEvent = new BeforeOrderRequestEvent($orderRequest, $context->payment, $paymentRequest, $action);
            $this->eventDispatcher->dispatch($beforeOrderEvent);
            $orderRequest = $beforeOrderEvent->getOrderRequest();

            /** @var array<string, mixed> $normalizedOrderRequest */
            $normalizedOrderRequest = $this->serializer->normalize($orderRequest);
            $redactedPayload = $this->payloadRedactor->redact($normalizedOrderRequest);
            $paymentRequest->setPayload($redactedPayload);
            $paymentRequest->setAction($action);

            if ($beforeOrderEvent->isApiCallSkipped()) {
                $alternative = $beforeOrderEvent->getAlternativeResponseData();
                if (null === $alternative) {
                    throw new LogicException('BeforeOrderRequestEvent skipped the API call but alternative response data is null.');
                }
                $paymentRequest->setResponseData($alternative);
                $this->dispatchAfterPaymentProcessedEvent($context->payment, $paymentRequest, $paymentRequest->getResponseData(), $action);
                $this->applyPaymentOutcomeFromStoredResponse($context->account, $paymentRequest, $context->payment);

                return;
            }

            $this->logger->info('[Hipay][NewOrderRequestHandler] Before sending request', [
                'request_id' => $paymentRequest->getId(),
                'action' => $action,
                'order_request' => $redactedPayload,
            ]);

            $transaction = $this->hiPayClientProvider->getForAccount($context->account)->requestNewOrder($orderRequest);
            $this->saveTransaction($context->payment, $transaction);
            /** @var array<string, mixed> $normalizedTransaction */
            $normalizedTransaction = $this->serializer->normalize($transaction);
            $paymentRequest->setResponseData($normalizedTransaction);

            $this->logger->info('[Hipay][NewOrderRequestHandler] After sending request', [
                'request_id' => $paymentRequest->getId(),
                'action' => $action,
                'transaction' => $this->payloadRedactor->redact($normalizedTransaction),
            ]);

            $this->dispatchAfterPaymentProcessedEvent($context->payment, $paymentRequest, $paymentRequest->getResponseData(), $action);
            $this->applyPaymentOutcomeFromStoredResponse($context->account, $paymentRequest, $context->payment);
        } catch (Exception $e) {
            $this->logger->error('[Hipay][NewOrderRequestHandler] Error during request', [
                'request_id' => $paymentRequest->getId(),
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            // A failed flush closes the EntityManager; recording the failure would then
            // throw EntityManagerClosed and mask the original error. Nothing can be
            // persisted anymore, so stop here and let the logged error be the trace.
            if (!$this->entityManager->isOpen()) {
                return;
            }

            $paymentRequest->setResponseData([
                'error' => $e->getMessage(),
                'state' => TransactionState::ERROR,
            ]);

            $this->dispatchAfterPaymentProcessedEvent($context->payment, $paymentRequest, $paymentRequest->getResponseData(), $action);
            $this->setAllFailState($paymentRequest, $context->payment);
        } finally {
            if ($this->entityManager->isOpen()) {
                $this->entityManager->flush();
            }
        }
    }

    /**
     * Creates the hipay_transaction row for the reference HiPay just returned.
     *
     * This handler is not the only writer: the webhook notification can reach us before
     * we get here and create the same row from custom_data.payment_id, in
     * NotificationProcessor::resolvePaymentAndEnsureTransaction(). Statuses 116/117/118/142
     * are now processed synchronously (see Webhook\Consumer), so the buffer delay no longer
     * hides that race — hence the existence check plus a write that tolerates the duplicate.
     */
    protected function saveTransaction(PaymentInterface $payment, Transaction $transaction): void
    {
        $reference = $transaction->getTransactionReference();

        if (null !== $this->transationRepository->findOneBy(['transactionReference' => $reference])) {
            return;
        }

        $metadata = $this->entityManager->getClassMetadata($this->transationRepository->getClassName());
        $paymentColumn = $metadata->getSingleAssociationJoinColumnName('payment');
        $referenceColumn = $metadata->getColumnName('transactionReference');
        $createdAtColumn = $metadata->getColumnName('createdAt');

        // Written through the DBAL rather than persist()/flush() on purpose: should the
        // webhook still win the race on the transaction_reference unique index, the
        // violation surfaces here without closing the EntityManager. A failed flush()
        // would close it, and everything after this point — payment state, event
        // dispatch, the flush in the finally block — would fail in cascade with
        // "The EntityManager is closed", leaving a payment captured at HiPay recorded
        // nowhere on our side.
        //
        // The savepoint keeps the surrounding transaction (opened by Messenger's
        // DoctrineTransactionMiddleware) usable after the violation: MySQL only rolls
        // back the offending statement, but PostgreSQL aborts the whole transaction.
        $connection = $this->entityManager->getConnection();
        $savepoint = null;
        if ($connection->isTransactionActive() && $connection->getDatabasePlatform()->supportsSavepoints()) {
            $savepoint = 'hipay_transaction_' . bin2hex(random_bytes(4));
            $connection->createSavepoint($savepoint);
        }

        try {
            $connection->insert(
                $metadata->getTableName(),
                [
                    $paymentColumn => $payment->getId(),
                    $referenceColumn => $reference,
                    $createdAtColumn => new DateTime(),
                ],
                [
                    $paymentColumn => Types::INTEGER,
                    $referenceColumn => Types::STRING,
                    $createdAtColumn => Types::DATETIME_MUTABLE,
                ],
            );

            if (null !== $savepoint) {
                $connection->releaseSavepoint($savepoint);
            }
        } catch (UniqueConstraintViolationException) {
            if (null !== $savepoint) {
                $connection->rollbackSavepoint($savepoint);
            }

            $this->logger->info('[Hipay][NewOrderRequestHandler] Transaction already stored by the webhook', [
                'transaction_reference' => $reference,
                'payment_id' => $payment->getId(),
            ]);
        }
    }

    protected function manageSentinel(AccountInterface $account, PaymentRequestInterface $paymentRequest, PaymentInterface $payment): void
    {
        $this->setOnHold($paymentRequest, $payment);
        $this->fraudSuspicionEmailManager->sendFraudSuspicionEmail($account, $payment);
    }

    protected function applyPaymentOutcomeFromStoredResponse(
        AccountInterface $account,
        PaymentRequestInterface $paymentRequest,
        PaymentInterface $payment,
    ): void {
        $data = $paymentRequest->getResponseData();
        if (!is_array($data)) {
            $this->setAllFailState($paymentRequest, $payment);

            return;
        }

        $state = $data['state'] ?? TransactionState::ERROR;
        match ($state) {
            TransactionState::DECLINED, TransactionState::ERROR => $this->setAllFailState($paymentRequest, $payment),
            TransactionState::PENDING => $this->manageSentinel($account, $paymentRequest, $payment),
            default => $this->setCompleteState($paymentRequest),
        };
    }
}
