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

final class PaymentEligibilityValidatorRegistry implements PaymentEligibilityValidatorRegistryInterface
{
    private array $validators = [];

    public function __construct(iterable $validators)
    {
        foreach ($validators as $validator) {
            $this->validators[] = $validator;
        }
    }

    public function get(string $paymentProduct): array
    {
        $validators = [];
        foreach ($this->validators as $validator) {
            if ($validator->supports($paymentProduct)) {
                $validators[] = $validator;
            }
        }

        return $validators;
    }
}
