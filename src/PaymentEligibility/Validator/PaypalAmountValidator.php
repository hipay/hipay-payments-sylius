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

namespace HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator;

use HiPay\SyliusHiPayPlugin\PaymentEligibility\PaymentEligibilityValidationResult;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\PaymentEligibilityValidatorInterface;
use Sylius\Component\Core\Model\PaymentInterface;

/**
 * PayPal SDK requires a positive amount in the request object.
 *
 * @see https://developer.hipay.com/online-payments/payment-means/paypal
 */
class PaypalAmountValidator implements PaymentEligibilityValidatorInterface
{
    private string $code = 'paypal';

    protected string $message = 'sylius_hipay_plugin.checkout.paypal.amount_invalid';

    public function validate(?PaymentInterface $payment): ?PaymentEligibilityValidationResult
    {
        if (null === $payment) {
            return null;
        }

        $amount = $payment->getAmount();

        if (null === $amount || $amount <= 0) {
            return new PaymentEligibilityValidationResult($this->message);
        }

        return null;
    }

    public function supports(string $paymentProduct): bool
    {
        return $paymentProduct === $this->code;
    }
}
