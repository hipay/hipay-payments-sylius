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

namespace HiPay\SyliusHiPayPlugin\Payment;

interface PaymentPayloadRedactorInterface
{
    /**
     * Removes cardholder data from a normalized payment payload before it is stored or logged.
     *
     * Walks the array recursively and drops any key that denotes card data — regardless of
     * nesting depth and of naming convention (snake_case `card_expiry_month` or camelCase
     * `cardExpiryMonth`, `token` or `cardtoken`). Non-sensitive keys are preserved as-is.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function redact(array $payload): array;
}
