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
 * Builds an OrderRequest by running a chain of processors declared via DI.
 * One instance per payment product (card, apple_pay, paypal, etc.).
 */
final class PaymentOrderRequestBuilder implements PaymentOrderRequestBuilderInterface
{
    /** @var iterable<PaymentOrderRequestProcessorInterface> */
    private readonly iterable $processors;

    /**
     * @param string[]                                   $paymentProducts Product code this builder handles
     * @param iterable<PaymentOrderRequestProcessorInterface> $processors     Ordered processor chain
     */
    public function __construct(
        private readonly array $paymentProducts,
        iterable $processors,
    ) {
        $this->processors = $processors;
    }

    public function build(PaymentOrderRequestContext $context): OrderRequest
    {
        $orderRequest = new OrderRequest();

        foreach ($this->processors as $processor) {
            $processor->process($orderRequest, $context);
        }

        return $orderRequest;
    }

    public function supports(string $paymentProduct): bool
    {
        return in_array($paymentProduct, $this->paymentProducts, true);
    }
}
