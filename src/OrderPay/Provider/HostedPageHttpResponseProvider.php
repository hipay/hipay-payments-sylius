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

namespace HiPay\SyliusHiPayPlugin\OrderPay\Provider;

use HiPay\Fullservice\Enum\Transaction\TransactionState;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * After-pay HTTP response provider for the HiPay Hosted Page gateway.
 *
 * All behaviour is shared with the Hosted Fields provider via
 * {@see AbstractHostedHttpResponseProvider}.
 */
final class HostedPageHttpResponseProvider extends AbstractHostedHttpResponseProvider
{
    protected function resolveRedirect(string $state, ?string $tokenValue, array $data): RedirectResponse
    {
        return match ($state) {
            TransactionState::FORWARDING => $this->buildForwardingResponse($data),
            TransactionState::PENDING, TransactionState::COMPLETED => $this->buildThankYouResponse($tokenValue, $data),
            default => $this->buildOrderShowResponse($tokenValue, $data),
        };
    }
}
