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

namespace HiPay\SyliusHiPayPlugin\CommandProvider;

use HiPay\SyliusHiPayPlugin\Command\NewOrderRequest;
use HiPay\SyliusHiPayPlugin\Command\TransactionInformationRequest;
use InvalidArgumentException;
use Sylius\Bundle\PaymentBundle\CommandProvider\PaymentRequestCommandProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final readonly class HostedFieldsPaymentRequestCommandProvider implements PaymentRequestCommandProviderInterface
{
    public function supports(PaymentRequestInterface $paymentRequest): bool
    {
        return in_array($paymentRequest->getAction(), [
            PaymentRequestInterface::ACTION_CAPTURE,
            PaymentRequestInterface::ACTION_AUTHORIZE,
            PaymentRequestInterface::ACTION_STATUS,
        ], true);
    }

    public function provide(PaymentRequestInterface $paymentRequest): object
    {
        $hash = $paymentRequest->getHash();

        return match ($paymentRequest->getAction()) {
            PaymentRequestInterface::ACTION_CAPTURE,
            PaymentRequestInterface::ACTION_AUTHORIZE => new NewOrderRequest(null !== $hash ? (string) $hash : null),
            PaymentRequestInterface::ACTION_STATUS => new TransactionInformationRequest(null !== $hash ? (string) $hash : null),
            default => throw new InvalidArgumentException('Unsupported payment request action'),
        };
    }
}
