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

namespace HiPay\SyliusHiPayPlugin\Payment\Capture\Request\Processor;

use HiPay\Fullservice\Enum\Transaction\Operation;
use HiPay\Fullservice\Gateway\Request\Maintenance\MaintenanceRequest;
use HiPay\SyliusHiPayPlugin\Payment\Capture\Request\CaptureRequestContext;
use HiPay\SyliusHiPayPlugin\Payment\Capture\Request\CaptureRequestProcessorInterface;
use HiPay\SyliusHiPayPlugin\Util\CurrencyAmount;
use function sprintf;

/**
 * Sets the maintenance operation, its unique operation id and the (full) amount.
 * operation_id format: capture-{ORDER_NUMBER}-{PAYMENT_ID}.
 */
final class CaptureRequestOperationProcessor implements CaptureRequestProcessorInterface
{
    public function process(MaintenanceRequest $request, CaptureRequestContext $context): void
    {
        $order = $context->order;
        $payment = $context->payment;

        $request->operation = Operation::CAPTURE;
        $request->operation_id = sprintf('capture-%s-%s', $order->getNumber() ?? (string) $order->getId(), (string) $payment->getId());
        // Full-amount capture only. Payment amount is in minor units; HiPay expects major units.
        $request->amount = CurrencyAmount::fromSmallestUnit($payment->getAmount() ?? 0, (string) $order->getCurrencyCode());
    }
}
