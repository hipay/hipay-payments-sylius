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
 * Mutates a capture MaintenanceRequest by setting specific fields from the context.
 *
 * Contract: processors MUST only read from $context and write to $request.
 * They MUST NOT read fields on $request set by other processors.
 */
interface CaptureRequestProcessorInterface
{
    public function process(MaintenanceRequest $request, CaptureRequestContext $context): void;
}
