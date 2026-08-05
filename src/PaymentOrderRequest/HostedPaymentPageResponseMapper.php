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

use HiPay\Fullservice\Enum\Transaction\TransactionState;
use HiPay\Fullservice\Gateway\Model\HostedPaymentPage;
use RuntimeException;

/**
 * Normalizes a HiPay HostedPaymentPage API response into PaymentRequest responseData
 * consumed by HostedPageHttpResponseProvider.
 */
final class HostedPaymentPageResponseMapper
{
    /**
     * @return array<string, mixed>
     */
    public function map(HostedPaymentPage $hostedPaymentPage): array
    {
        $forwardUrl = $hostedPaymentPage->getForwardUrl();
        if ('' === $forwardUrl) {
            throw new RuntimeException('HiPay Hosted Page response did not contain a forward URL.');
        }

        return [
            'state' => TransactionState::FORWARDING,
            'forwardUrl' => $forwardUrl,
            'mid' => $hostedPaymentPage->getMid(),
        ];
    }
}
