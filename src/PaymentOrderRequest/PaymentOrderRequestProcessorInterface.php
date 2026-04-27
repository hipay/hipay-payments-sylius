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

namespace HiPay\SyliusHiPayPlugin\PaymentOrderRequest;

use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;

/**
 * Mutates an OrderRequest by setting specific fields from the context.
 *
 * Contract: processors MUST only read from $context and write to $orderRequest.
 * They MUST NOT read fields on $orderRequest set by other processors.
 */
interface PaymentOrderRequestProcessorInterface
{
    public function process(OrderRequest $orderRequest, PaymentOrderRequestContext $context): void;
}
