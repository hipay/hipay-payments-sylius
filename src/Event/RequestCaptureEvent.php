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

namespace HiPay\SyliusHiPayPlugin\Event;

use HiPay\SyliusHiPayPlugin\Payment\Capture\CaptureRequestResult;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatch this event to trigger a capture
 *
 * Carries the id of the payment to capture (the amount is read from the payment —
 * always the full amount; the order is derived from the payment). The plugin listener
 * performs the capture and sets the {@see CaptureRequestResult} back on the event, which
 * the dispatcher can read afterwards.
 */
final class RequestCaptureEvent extends Event
{
    private ?CaptureRequestResult $result = null;

    public function __construct(
        private readonly int $paymentId,
    ) {
    }

    public function getPaymentId(): int
    {
        return $this->paymentId;
    }

    public function setResult(CaptureRequestResult $result): void
    {
        $this->result = $result;
    }

    public function getResult(): ?CaptureRequestResult
    {
        return $this->result;
    }
}
