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

use HiPay\SyliusHiPayPlugin\Client\ClientProviderInterface;
use HiPay\SyliusHiPayPlugin\Exception\PaymentActionException;
use function is_scalar;
use function sprintf;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequest;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Throwable;
use Webmozart\Assert\Assert;

final class ManualCaptureProcessor implements ManualCaptureProcessorInterface
{
    public function __construct(
        private readonly ClientProviderInterface $hiPayClientProvider,
        private readonly StateMachineInterface $stateMachine,
        private readonly RepositoryInterface $paymentRequestRepository,
    ) {
    }

    public function process(PaymentInterface $payment): void
    {
        $paymentMethod = $this->validatePayment($payment);
        $transactionReference = $this->getTransactionReference($payment);
        [$amount, $currency] = $this->getCaptureDetails($payment);

        // Create a new PaymentRequest for the capture action
        // PaymentRequest constructor requires PaymentInterface and PaymentMethodInterface
        $capturePaymentRequest = new PaymentRequest($payment, $paymentMethod);
        $capturePaymentRequest->setAction(PaymentRequestInterface::ACTION_CAPTURE);

        // Create capture request payload
        $capturePayload = [
            'operation' => 'capture',
            'transaction_reference' => $transactionReference,
            'amount' => $amount,
            'currency' => $currency,
        ];
        $capturePaymentRequest->setPayload($capturePayload);

        try {
            // Get HiPay client and capture payment
            $hiPayClient = $this->hiPayClientProvider->getForPaymentMethod($paymentMethod);
            $captureResponse = $hiPayClient->capturePayment((string) $transactionReference, $amount, $currency);

            // Save capture response data
            $capturePaymentRequest->setResponseData($captureResponse);

            // Mark PaymentRequest as complete
            if ($this->stateMachine->can($capturePaymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE)) {
                $this->stateMachine->apply(
                    $capturePaymentRequest,
                    PaymentRequestTransitions::GRAPH,
                    PaymentRequestTransitions::TRANSITION_COMPLETE,
                );
            }

            // Persist the PaymentRequest
            $this->paymentRequestRepository->add($capturePaymentRequest);

            // Update payment state to completed
            // Try capture transition first (if configured in state machine)
            if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, 'capture')) {
                $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, 'capture');
            } elseif ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE)) {
                // Fallback to complete transition if capture doesn't exist
                $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);
            }
        } catch (Throwable $e) {
            // Mark PaymentRequest as failed
            if ($this->stateMachine->can($capturePaymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL)) {
                $this->stateMachine->apply(
                    $capturePaymentRequest,
                    PaymentRequestTransitions::GRAPH,
                    PaymentRequestTransitions::TRANSITION_FAIL,
                );
            }

            // Save error in response data
            $capturePaymentRequest->setResponseData([
                'error' => true,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            // Persist the PaymentRequest even on failure
            $this->paymentRequestRepository->add($capturePaymentRequest);

            // Re-throw the exception
            throw PaymentActionException::create(sprintf('Failed to capture payment: %s', $e->getMessage()));
        }
    }

    /**
     * Validate payment and return payment method.
     */
    private function validatePayment(PaymentInterface $payment): PaymentMethodInterface
    {
        $paymentMethod = $payment->getMethod();
        if (!$paymentMethod instanceof PaymentMethodInterface) {
            throw PaymentActionException::create(sprintf('Payment (ID: %s) must have a payment method', $payment->getId()));
        }

        // Check if payment method is HiPay
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (null === $gatewayConfig || 'hipay' !== $gatewayConfig->getFactoryName()) {
            throw PaymentActionException::create(sprintf('Cannot capture non HiPay payment (ID: %s)', $payment->getId()));
        }

        // Check capture mode
        $config = $gatewayConfig->getConfig();
        $captureMode = $config['capture_mode'] ?? 'capture';
        if ('capture' === $captureMode) {
            throw PaymentActionException::create(sprintf('Cannot manually capture payment (ID: %s) with automatic capture mode', $payment->getId()));
        }

        // Check payment state
        if (PaymentInterface::STATE_AUTHORIZED !== $payment->getState()) {
            throw PaymentActionException::create(sprintf('Cannot capture payment (ID: %s) that is not in authorized state, current state: %s', $payment->getId(), $payment->getState()));
        }

        return $paymentMethod;
    }

    /**
     * Get transaction reference from payment details.
     */
    private function getTransactionReference(PaymentInterface $payment): string
    {
        $details = $payment->getDetails();
        $transactionReference = $details['reference'] ?? $details['transaction_reference'] ?? null;
        if (null === $transactionReference || !is_scalar($transactionReference)) {
            throw PaymentActionException::create(sprintf('Cannot capture payment (ID: %s): transaction reference not found in payment details', $payment->getId()));
        }

        return (string) $transactionReference;
    }

    /**
     * Get capture amount and currency from payment.
     *
     * @return array{0: float, 1: string}
     */
    private function getCaptureDetails(PaymentInterface $payment): array
    {
        $order = $payment->getOrder();
        Assert::notNull($order, 'Payment must have an order');
        $currency = $order->getCurrencyCode();
        Assert::notNull($currency, 'Order must have a currency code');

        // Calculate amount (payment amount is in cents, HiPay expects currency units)
        $amount = (float) ($payment->getAmount() / 100);

        return [$amount, $currency];
    }
}
