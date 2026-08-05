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

use HiPay\SyliusHiPayPlugin\Request\RequestContextInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

/**
 * Immutable value object carrying the data capture-request processors may need.
 */
final readonly class CaptureRequestContext implements RequestContextInterface
{
    public function __construct(
        public OrderInterface $order,
        public PaymentInterface $payment,
        public string $transactionReference,
    ) {
    }

    public function getOrder(): OrderInterface
    {
        return $this->order;
    }
}
