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

use HiPay\Fullservice\Gateway\Model\Request\ThreeDSTwo\BrowserInfo;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestProcessorInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\PaymentFallbackDefaultsInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Sets browser_info, device_fingerprint, and ipaddr from payload and HTTP request.
 */
final class BrowserInfoProcessorPayment implements PaymentOrderRequestProcessorInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(OrderRequest $orderRequest, PaymentOrderRequestContext $context): void
    {
        $payload = $context->payload;

        $rawFingerprint = $payload['device_fingerprint'] ?? '';
        $orderRequest->device_fingerprint = is_string($rawFingerprint) ? $rawFingerprint : '';

        $currentRequest = $this->requestStack->getCurrentRequest();
        $orderRequest->ipaddr = $currentRequest?->getClientIp() ?? '0.0.0.0';

        if (isset($payload['browser_info']) && is_array($payload['browser_info'])) {
            $browserInfo = new BrowserInfo();
            $browserInfo->java_enabled = (bool) ($payload['browser_info']['java_enabled'] ?? false);
            $browserInfo->javascript_enabled = (bool) ($payload['browser_info']['javascript_enabled'] ?? true);
            $browserInfo->language = (string) ($payload['browser_info']['language'] ?? strtolower(PaymentFallbackDefaultsInterface::COUNTRY_CODE));
            $browserInfo->color_depth = (int) ($payload['browser_info']['color_depth'] ?? 24);
            $browserInfo->screen_height = (int) ($payload['browser_info']['screen_height'] ?? 900);
            $browserInfo->screen_width = (int) ($payload['browser_info']['screen_width'] ?? 1600);
            $browserInfo->timezone = (string) ($payload['browser_info']['timezone'] ?? '0');
            $browserInfo->http_user_agent = (string) ($payload['browser_info']['http_user_agent'] ?? '');

            $orderRequest->browser_info = $browserInfo;
        }
    }
}
