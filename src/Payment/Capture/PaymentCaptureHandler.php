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

namespace HiPay\SyliusHiPayPlugin\Payment\Capture;

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Client\ClientProviderInterface;
use HiPay\SyliusHiPayPlugin\Client\HiPayClient;
use HiPay\SyliusHiPayPlugin\Payment\Capture\Request\CaptureRequestBuilderInterface;
use HiPay\SyliusHiPayPlugin\Payment\Capture\Request\CaptureRequestContext;
use HiPay\SyliusHiPayPlugin\Payment\OrderAdvisoryLockInterface;
use HiPay\SyliusHiPayPlugin\Payment\PaymentRequestAction;
use HiPay\SyliusHiPayPlugin\Provider\PaymentRequestProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\TransactionProviderInterface;
use function sprintf;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Throwable;
use Webmozart\Assert\Assert;

final class PaymentCaptureHandler implements PaymentCaptureHandlerInterface
{
    public function __construct(
        private readonly PaymentCaptureEligibilityCheckerInterface $eligibilityChecker,
        private readonly ClientProviderInterface $hiPayClientProvider,
        private readonly PaymentRequestProviderInterface $paymentRequestProvider,
        private readonly TransactionProviderInterface $hiPayTransactionProvider,
        private readonly CaptureRequestBuilderInterface $captureRequestBuilder,
        private readonly OrderAdvisoryLockInterface $orderAdvisoryLock,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function capture(PaymentInterface $payment): CaptureRequestResult
    {
        $order = $payment->getOrder();
        Assert::isInstanceOf($order, OrderInterface::class, 'Payment must have an order');

        $lockName = $this->orderAdvisoryLock->acquire($order);

        if (null === $lockName) {
            return CaptureRequestResult::failure(sprintf('Capture already in progress for payment (ID: %s)', $payment->getId()));
        }

        try {
            if (!$this->eligibilityChecker->isEligible($payment)) {
                return CaptureRequestResult::failure(sprintf('Payment (ID: %s) is not eligible for capture', $payment->getId()));
            }

            $paymentMethod = $payment->getMethod();
            Assert::isInstanceOf($paymentMethod, PaymentMethodInterface::class);

            $transactionReference = $this->hiPayTransactionProvider->getByPayment($payment)?->getTransactionReference();
            if (null === $transactionReference || '' === $transactionReference) {
                return CaptureRequestResult::failure(sprintf('Cannot capture payment (ID: %s): transaction reference not found', $payment->getId()));
            }

            // Build the capture MaintenanceRequest through the processor pipeline.
            $captureRequest = $this->captureRequestBuilder->build(
                new CaptureRequestContext($order, $payment, $transactionReference),
            );

            $paymentRequest = $this->paymentRequestProvider->createPaymentRequest(
                $payment,
                $paymentMethod,
                PaymentRequestAction::CAPTURE_REQUEST->value,
                HiPayClient::serializeRequest($captureRequest),
            );
            $this->entityManager->persist($paymentRequest);
            $this->paymentRequestProvider->setProcessState($paymentRequest);
            // Commit the pending capture request before calling HiPay so a concurrent
            // capture (e.g. one that timed out on the advisory lock) sees it via
            // hasPendingCaptureRequest() and is rejected — closing the double-capture window.
            $this->entityManager->flush();

            try {
                $response = $this->hiPayClientProvider
                    ->getForPaymentMethod($paymentMethod)
                    ->capturePayment($transactionReference, $captureRequest);

                // The request payload was already logged at creation (see above); here we
                // only record HiPay's response.
                $paymentRequest->setResponseData($response);
                $this->paymentRequestProvider->setCompleteState($paymentRequest);
                $this->entityManager->flush();
            } catch (Throwable $e) {
                $paymentRequest->setResponseData([
                    'error' => true,
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]);
                $this->paymentRequestProvider->setFailState($paymentRequest);
                $this->entityManager->flush();

                return CaptureRequestResult::failure(sprintf('Failed to capture payment (ID: %s): %s', $payment->getId(), $e->getMessage()));
            }

            // The Sylius payment intentionally stays "authorized": only the HiPay
            // "Captured" (118) notification transitions it to "paid".
            return CaptureRequestResult::success();
        } finally {
            $this->orderAdvisoryLock->release($lockName);
        }
    }
}
