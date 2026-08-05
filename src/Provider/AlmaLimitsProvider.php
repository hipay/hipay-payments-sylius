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

use HiPay\SyliusHiPayPlugin\Client\ClientProviderInterface;
use RuntimeException;
use Throwable;

final class AlmaLimitsProvider implements AlmaLimitsProviderInterface
{
    public function __construct(
        private readonly ClientProviderInterface $clientProvider,
    ) {
    }

    public function getLimits(string $accountCode, string $paymentProduct): ?array
    {
        [$minKey, $maxKey] = match ($paymentProduct) {
            'alma-3x' => ['basketAmountMin3x', 'basketAmountMax3x'],
            'alma-4x' => ['basketAmountMin4x', 'basketAmountMax4x'],
            default => throw new RuntimeException(sprintf('Unsupported Alma product "%s"', $paymentProduct)),
        };

        try {
            $client = $this->clientProvider->getForAccountCode($accountCode);
            $amounts = $client->getAlmaBasketAmounts();

            if (
                null === $amounts ||
                !isset($amounts[$minKey], $amounts[$maxKey]) ||
                !is_scalar($amounts[$minKey]) ||
                !is_scalar($amounts[$maxKey])
            ) {
                return null;
            }

            return ['min' => (float) $amounts[$minKey], 'max' => (float) $amounts[$maxKey]];
        } catch (Throwable) {
            return null;
        }
    }
}
