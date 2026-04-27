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
use HiPay\SyliusHiPayPlugin\Command\TransactionInformationRequest;
use HiPay\SyliusHiPayPlugin\Entity\TransactionInterface;
use RuntimeException;

readonly class TransactionInformationRequestHandler extends AbstractPaymentRequestHandler
{
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
                throw new RuntimeException('No transaction reference found');
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
                'transaction' => $normalizedTransaction,
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
