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

namespace HiPay\SyliusHiPayPlugin\Event;

use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after the OrderRequest has been built and before the HiPay API is called.
 *
 * Listeners can:
 *  - mutate the OrderRequest (add custom_data, change parameters)
 *  - short-circuit the API call by providing alternative response data
 */
final class BeforeOrderRequestEvent extends Event
{
    /** @var array<string, mixed>|null */
    private ?array $alternativeResponseData = null;

    public function __construct(
        private OrderRequest $orderRequest,
        private readonly PaymentInterface $payment,
        private readonly PaymentRequestInterface $paymentRequest,
        private readonly string $action,
    ) {
    }

    public function getOrderRequest(): OrderRequest
    {
        return $this->orderRequest;
    }

    public function setOrderRequest(OrderRequest $orderRequest): void
    {
        $this->orderRequest = $orderRequest;
    }

    public function getPayment(): PaymentInterface
    {
        return $this->payment;
    }

    public function getPaymentRequest(): PaymentRequestInterface
    {
        return $this->paymentRequest;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @param array<string, mixed> $responseData
     */
    public function setAlternativeResponseData(array $responseData): void
    {
        $this->alternativeResponseData = $responseData;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAlternativeResponseData(): ?array
    {
        return $this->alternativeResponseData;
    }

    public function isApiCallSkipped(): bool
    {
        return null !== $this->alternativeResponseData;
    }
}
