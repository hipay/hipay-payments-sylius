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

use Sylius\Component\Core\Model\PaymentInterface;

interface PaymentEligibilityValidatorInterface
{
    public function validate(?PaymentInterface $payment): ?PaymentEligibilityValidationResult;

    public function supports(string $paymentProduct): bool;
}
