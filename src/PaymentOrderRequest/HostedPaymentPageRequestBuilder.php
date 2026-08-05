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

use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;

/**
 * Builds a HostedPaymentPageRequest by running a chain of processors declared via DI.
 */
final class HostedPaymentPageRequestBuilder implements HostedPaymentPageRequestBuilderInterface
{
    /** @var iterable<PaymentOrderRequestProcessorInterface> */
    private readonly iterable $processors;

    /**
     * @param iterable<PaymentOrderRequestProcessorInterface> $processors
     */
    public function __construct(iterable $processors)
    {
        $this->processors = $processors;
    }

    public function build(PaymentOrderRequestContext $context): HostedPaymentPageRequest
    {
        $request = new HostedPaymentPageRequest();

        foreach ($this->processors as $processor) {
            $processor->process($request, $context);
        }

        return $request;
    }
}
