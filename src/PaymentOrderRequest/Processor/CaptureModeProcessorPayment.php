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

namespace HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor;

use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestProcessorInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Capture\CaptureMode;

/**
 * Sets the capture mode (`operation`) on every order request, derived from the
 * gateway-config root `use_authorize` flag. Shared by all payment products
 * (card, hosted page and the rest). Defaults to instant capture (Sale).
 */
final class CaptureModeProcessorPayment implements PaymentOrderRequestProcessorInterface
{
    public function process(OrderRequest $orderRequest, PaymentOrderRequestContext $context): void
    {
        $useAuthorize = (bool) ($context->gatewayConfig['use_authorize'] ?? false);
        $orderRequest->operation = CaptureMode::operationFromUseAuthorize($useAuthorize);
    }
}
