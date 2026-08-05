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

namespace HiPay\SyliusHiPayPlugin\Request\Processor;

use Composer\InstalledVersions;
use HiPay\Fullservice\Gateway\Request\CommonRequest;
use HiPay\SyliusHiPayPlugin\Payment\Capture\Request\CaptureRequestProcessorInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestProcessorInterface;
use HiPay\SyliusHiPayPlugin\Request\RequestContextInterface;

/**
 * Sets the CMS/plugin technical source on any CommonRequest (OrderRequest, capture
 * MaintenanceRequest), so it serves both the order-request and capture pipelines.
 */
final class SourceProcessor implements PaymentOrderRequestProcessorInterface, CaptureRequestProcessorInterface
{
    private const PLUGIN_VERSION = '1.0.0';

    public function process(CommonRequest $request, RequestContextInterface $context): void
    {
        unset($context);

        $syliusVersion = InstalledVersions::getPrettyVersion('sylius/sylius')
            ?? InstalledVersions::getPrettyVersion('sylius/core-bundle')
            ?? 'unknown';

        $request->source = [
            'source' => 'CMS',
            'brand' => 'Sylius',
            'brand_version' => $syliusVersion,
            'integration_version' => self::PLUGIN_VERSION,
        ];
    }
}
