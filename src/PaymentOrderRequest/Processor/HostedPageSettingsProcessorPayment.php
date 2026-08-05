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

use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestProcessorInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\HostedPageConfigurationDefaultsInterface;

/**
 * Maps gateway hosted_page settings onto HostedPaymentPageRequest.
 */
final class HostedPageSettingsProcessorPayment implements PaymentOrderRequestProcessorInterface
{
    public function process(OrderRequest $orderRequest, PaymentOrderRequestContext $context): void
    {
        if (!$orderRequest instanceof HostedPaymentPageRequest) {
            return;
        }

        $hostedPage = $context->gatewayConfig['hosted_page'] ?? [];
        if (!is_array($hostedPage)) {
            return;
        }

        $orderRequest->template = HostedPageConfigurationDefaultsInterface::TEMPLATE;

        $orderRequest->theme_code = $hostedPage['theme_code'] ?? '';

        $orderRequest->display_selector = 1;
        $orderRequest->display_cancel_button = $hostedPage['display_cancel_button'] ?? false;

        $timeLimit = $hostedPage['time_limit_to_pay'] ?? HostedPageConfigurationDefaultsInterface::TIME_LIMIT_TO_PAY;
        if ($timeLimit > 0) {
            $orderRequest->time_limit_to_pay = (int) $timeLimit;
        }
    }
}
