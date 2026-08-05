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

namespace HiPay\SyliusHiPayPlugin\EventListener;

use HiPay\SyliusHiPayPlugin\Event\RequestCaptureEvent;
use HiPay\SyliusHiPayPlugin\Payment\Capture\CaptureRequestResult;
use HiPay\SyliusHiPayPlugin\Payment\Capture\PaymentCaptureHandlerInterface;
use function sprintf;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * Performs the capture of the payment targeted by a {@see RequestCaptureEvent}
 * (the order is derived from the payment), and reports the outcome on the event.
 */
final class RequestCaptureListener
{
    public function __construct(
        private readonly RepositoryInterface $paymentRepository,
        private readonly PaymentCaptureHandlerInterface $captureHandler,
    ) {
    }

    public function __invoke(RequestCaptureEvent $event): void
    {
        $paymentId = $event->getPaymentId();

        $payment = $this->paymentRepository->find($paymentId);
        if (!$payment instanceof PaymentInterface) {
            $event->setResult(CaptureRequestResult::failure(sprintf('Payment #%s not found.', $paymentId)));

            return;
        }

        $event->setResult($this->captureHandler->capture($payment));
    }
}
