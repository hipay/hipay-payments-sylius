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

final class PaymentPayloadRedactor implements PaymentPayloadRedactorInterface
{
    /**
     * Canonical (lowercased, underscore-stripped) names of keys carrying card data.
     */
    private const SENSITIVE_KEYS = [
        'token',
        'cardtoken',
        'cardid',
        'cardnumber',
        'pan',
        'cvc',
        'cvv',
        'cvcresult',
        'cvvresult',
        'cardsecuritycode',
        'cardholder',
        'cardexpirymonth',
        'cardexpiryyear',
        'cardexpirydate',
    ];

    public function redact(array $payload): array
    {
        $result = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array($this->canonicalize($key), self::SENSITIVE_KEYS, true)) {
                continue;
            }

            $result[$key] = is_array($value) ? $this->redact($value) : $value;
        }

        return $result;
    }

    private function canonicalize(string $key): string
    {
        return str_replace('_', '', strtolower($key));
    }
}
