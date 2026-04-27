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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

/**
 * PayPal SDK requires a valid ISO 4217 currency code in the request object.
 *
 * @see https://developer.hipay.com/online-payments/payment-means/paypal
 */
class PaypalCurrencyValidator implements PaymentEligibilityValidatorInterface
{
    private string $code = 'paypal';

    protected string $message = 'sylius_hipay_plugin.checkout.paypal.currency_invalid';

    public function validate(?PaymentInterface $payment): ?PaymentEligibilityValidationResult
    {
        /** @var OrderInterface|null $order */
        $order = $payment?->getOrder();
        if (null === $order) {
            return null;
        }

        $currencyCode = $order->getCurrencyCode();

        if (null === $currencyCode || '' === $currencyCode) {
            return new PaymentEligibilityValidationResult($this->message);
        }

        return null;
    }

    public function supports(string $paymentProduct): bool
    {
        return $paymentProduct === $this->code;
    }
}
