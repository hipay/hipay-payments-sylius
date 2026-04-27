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

namespace HiPay\SyliusHiPayPlugin\PaymentEligibility;

final class PaymentEligibilityValidationResult
{
    public function __construct(
        public readonly string $message,
        public readonly array $parameters = [],
    ) {
    }
}
