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
 * PayPal SDK requires a complete shipping address with zipCode, city, country
 * and streetaddress in the customerShippingInformation request object.
 *
 * @see https://developer.hipay.com/online-payments/payment-means/paypal
 */
class PaypalShippingAddressValidator implements PaymentEligibilityValidatorInterface
{
    private string $code = 'paypal';

    protected string $message = 'sylius_hipay_plugin.checkout.paypal.shipping_address_incomplete';

    public function validate(?PaymentInterface $payment): ?PaymentEligibilityValidationResult
    {
        /** @var OrderInterface|null $order */
        $order = $payment?->getOrder();
        if (null === $order) {
            return null;
        }

        $shippingAddress = $order->getShippingAddress();
        if (null === $shippingAddress) {
            return new PaymentEligibilityValidationResult($this->message);
        }

        $missingFields = [];

        if ($this->isBlank($shippingAddress->getStreet())) {
            $missingFields[] = 'streetaddress';
        }

        if ($this->isBlank($shippingAddress->getCity())) {
            $missingFields[] = 'city';
        }

        if ($this->isBlank($shippingAddress->getPostcode())) {
            $missingFields[] = 'zipCode';
        }

        if ($this->isBlank($shippingAddress->getCountryCode())) {
            $missingFields[] = 'country';
        }

        if ([] !== $missingFields) {
            return new PaymentEligibilityValidationResult(
                $this->message,
                ['%fields%' => implode(', ', $missingFields)],
            );
        }

        return null;
    }

    public function supports(string $paymentProduct): bool
    {
        return $paymentProduct === $this->code;
    }

    protected function isBlank(?string $value): bool
    {
        return null === $value || '' === trim($value);
    }
}
