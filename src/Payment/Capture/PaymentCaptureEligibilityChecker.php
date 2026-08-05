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

use HiPay\SyliusHiPayPlugin\Payment\HiPayStatus;
use HiPay\SyliusHiPayPlugin\Payment\PaymentRequestAction;
use HiPay\SyliusHiPayPlugin\Provider\GatewayProvider;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class PaymentCaptureEligibilityChecker implements PaymentCaptureEligibilityCheckerInterface
{
    public function __construct(
        private readonly RepositoryInterface $paymentRequestRepository,
    ) {
    }

    public function isEligible(PaymentInterface $payment): bool
    {
        if (!GatewayProvider::isHiPayGateway($payment->getMethod())) {
            return false;
        }

        // HIPA37-1: Sylius payment state must be "authorized".
        if (PaymentInterface::STATE_AUTHORIZED !== $payment->getState()) {
            return false;
        }

        // HIPA37-1: latest HiPay status must be "Authorized" (116). Once a
        // capture is requested HiPay reports 117 (CaptureRequested), so this
        // alone already hides the button after the notification arrives.
        if (HiPayStatus::Authorized->value !== $this->getLatestHiPayStatus($payment)) {
            return false;
        }

        // HIPA37-2: a second capture request is impossible. Covers the window
        // between the BO click and the arrival of the 117 notification.
        if ($this->hasPendingCaptureRequest($payment)) {
            return false;
        }

        return true;
    }

    private function getLatestHiPayStatus(PaymentInterface $payment): ?int
    {
        /** @var PaymentRequestInterface[] $requests */
        $requests = $this->paymentRequestRepository->findBy(['payment' => $payment], ['createdAt' => 'DESC']);

        foreach ($requests as $request) {
            $payload = $request->getPayload();
            $status = is_array($payload) ? ($payload['status'] ?? null) : null;
            if (is_numeric($status)) {
                return (int) $status;
            }
        }

        return null;
    }

    private function hasPendingCaptureRequest(PaymentInterface $payment): bool
    {
        /** @var PaymentRequestInterface[] $requests */
        $requests = $this->paymentRequestRepository->findBy([
            'payment' => $payment,
            'action' => PaymentRequestAction::CAPTURE_REQUEST->value,
        ]);

        foreach ($requests as $request) {
            // A failed request must not block a retry from the back office.
            if (PaymentRequestInterface::STATE_FAILED !== $request->getState()) {
                return true;
            }
        }

        return false;
    }
}
