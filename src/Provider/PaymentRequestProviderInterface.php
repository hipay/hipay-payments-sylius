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

namespace HiPay\SyliusHiPayPlugin\Provider;

use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

interface PaymentRequestProviderInterface
{
    public function createPaymentRequest(PaymentInterface $payment, PaymentMethodInterface $paymentMethod, string $action, array $payload): PaymentRequestInterface;

    public function setProcessState(PaymentRequestInterface $paymentRequest): void;

    public function setCancelState(PaymentRequestInterface $paymentRequest): void;

    public function setCompleteState(PaymentRequestInterface $paymentRequest): void;
}
