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

use Exception;
use HiPay\Fullservice\Enum\Transaction\TransactionState;
use HiPay\Fullservice\Gateway\Model\Transaction;
use HiPay\SyliusHiPayPlugin\Command\NewOrderRequest;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Event\BeforeOrderRequestEvent;
use HiPay\SyliusHiPayPlugin\Payment\PaymentRequestAction;
use LogicException;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

readonly class NewOrderRequestHandler extends AbstractPaymentRequestHandler
{
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

            $normalizedOrderRequest = $this->serializer->normalize($orderRequest);
            $paymentRequest->setPayload($normalizedOrderRequest);
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
                'order_request' => $normalizedOrderRequest,
            ]);

            $transaction = $this->hiPayClientProvider->getForAccount($context->account)->requestNewOrder($orderRequest);
            $this->saveTransaction($context->payment, $transaction);
            /** @var array<string, mixed> $normalizedTransaction */
            $normalizedTransaction = $this->serializer->normalize($transaction);
            $paymentRequest->setResponseData($normalizedTransaction);

            $this->logger->info('[Hipay][NewOrderRequestHandler] After sending request', [
                'request_id' => $paymentRequest->getId(),
                'action' => $action,
                'transaction' => $normalizedTransaction,
            ]);

            $this->dispatchAfterPaymentProcessedEvent($context->payment, $paymentRequest, $paymentRequest->getResponseData(), $action);
            $this->applyPaymentOutcomeFromStoredResponse($context->account, $paymentRequest, $context->payment);
        } catch (Exception $e) {
            $paymentRequest->setResponseData([
                'error' => $e->getMessage(),
                'state' => TransactionState::ERROR,
            ]);

            $this->logger->error('[Hipay][NewOrderRequestHandler] Error during request', [
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

    protected function saveTransaction(PaymentInterface $payment, Transaction $transaction): void
    {
        $entity = $this->transactionFactory->createNew();
        $entity->setPayment($payment);
        $entity->setTransactionReference($transaction->getTransactionReference());
        $this->entityManager->persist($entity);
        // Have to immediately flush because Hipay webhook calls quickly after the transaction is created
        $this->entityManager->flush();
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
