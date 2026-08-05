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

namespace HiPay\SyliusHiPayPlugin\Payment\Capture\Request;

use HiPay\Fullservice\Gateway\Request\Maintenance\MaintenanceRequest;

/**
 * Builds a capture MaintenanceRequest by running a chain of processors declared via DI.
 */
final class CaptureRequestBuilder implements CaptureRequestBuilderInterface
{
    /** @var iterable<CaptureRequestProcessorInterface> */
    private readonly iterable $processors;

    /**
     * @param iterable<CaptureRequestProcessorInterface> $processors
     */
    public function __construct(iterable $processors)
    {
        $this->processors = $processors;
    }

    public function build(CaptureRequestContext $context): MaintenanceRequest
    {
        $request = new MaintenanceRequest();

        foreach ($this->processors as $processor) {
            $processor->process($request, $context);
        }

        return $request;
    }
}
