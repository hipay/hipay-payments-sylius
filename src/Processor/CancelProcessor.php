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

namespace HiPay\SyliusHiPayPlugin\Processor;

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Client\ClientProviderInterface;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\GatewayProvider;
use HiPay\SyliusHiPayPlugin\Provider\PaymentRequestProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\TransactionProviderInterface;
use RuntimeException;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Resource\Exception\UpdateHandlingException;
use Webmozart\Assert\Assert;

class CancelProcessor implements CancelProcessorInterface
{
    public function __construct(
        private readonly ClientProviderInterface $hiPayClientProvider,
        private readonly TransactionProviderInterface $hiPayTransactionProvider,
        private readonly AccountProviderInterface $accountProvider,
        private readonly HiPayLoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentRequestProviderInterface $paymentRequestProvider,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function cancel(PaymentInterface $payment): void
    {
        $paymentMethod = $payment->getMethod();
        Assert::isInstanceOf($paymentMethod, PaymentMethodInterface::class, 'Payment must have a PaymentMethodConfiguration');

        if (false === GatewayProvider::isHiPayGateway($paymentMethod)) {
            return;
        }

        if ($payment->getState() !== PaymentInterface::STATE_AUTHORIZED) {
            $this->logger->warning('Attempt to cancel an unauthorized payment.', ['payment_id' => $payment->getId()]);

            throw new UpdateHandlingException(flash: 'hipay.error.payment_already_captured');
        }

        $transactionReference = $this->hiPayTransactionProvider->getByPayment($payment)?->getTransactionReference();
        if (null === $transactionReference) {
            $this->logger->warning('HiPay transaction reference not found for this payment.', ['payment_id' => $payment->getId()]);

            throw new UpdateHandlingException(flash: 'hipay.error.transaction_reference_not_found');
        }
        $account = $this->accountProvider->getByPaymentMethod($paymentMethod);
        if (null === $account || '' === $account->getCode()) {
            $this->logger->warning('HiPay account not found for this payment.', ['payment_id' => $payment->getId()]);

            throw new UpdateHandlingException(flash: 'hipay.error.account_not_found');
        }
        $this->logger->setAccount($account);
        $hipayClient = $this->hiPayClientProvider->getForAccountCode($account->getCode());

        try {
            $response = $hipayClient->cancelPayment($transactionReference);
            $paymentRequest = $this->paymentRequestProvider->createPaymentRequest($payment, $paymentMethod, PaymentRequestInterface::ACTION_CANCEL, $response);
            $this->entityManager->persist($paymentRequest);
            $this->paymentRequestProvider->setProcessState($paymentRequest);
            $details = $payment->getDetails();
            $details['cancel_response'] = $response;
            $payment->setDetails($details);
            $this->entityManager->persist($payment);
            $this->paymentRequestProvider->setCompleteState($paymentRequest);
            $this->entityManager->flush();
            $this->logger->info('HiPay cancellation successful.', ['payment_id' => $payment->getId(), 'transaction' => $transactionReference]);
        } catch (RuntimeException $e) {
            $this->logger->error('The cancellation failed on HiPay : ' . $e->getMessage(), ['payment_id' => $payment->getId(), 'transaction' => $transactionReference]);

            throw new UpdateHandlingException(flash: 'hipay.error.cancellation_failed');
        }
    }
}
