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

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after HiPay API response has been processed, before flush and redirect.
 *
 * Listeners can:
 *  - read/mutate the response data
 *  - override the HTTP redirect response entirely
 */
final class AfterPaymentProcessedEvent extends Event
{
    private ?Response $response = null;

    /**
     * @param array<string, mixed> $responseData
     */
    public function __construct(
        private readonly PaymentInterface $payment,
        private readonly PaymentRequestInterface $paymentRequest,
        private array $responseData,
        private readonly string $action,
    ) {
    }

    public function getPayment(): PaymentInterface
    {
        return $this->payment;
    }

    public function getPaymentRequest(): PaymentRequestInterface
    {
        return $this->paymentRequest;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponseData(): array
    {
        return $this->responseData;
    }

    /**
     * @param array<string, mixed> $responseData
     */
    public function setResponseData(array $responseData): void
    {
        $this->responseData = $responseData;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function hasCustomResponse(): bool
    {
        return null !== $this->response;
    }
}
