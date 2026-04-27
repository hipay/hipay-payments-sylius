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
 * Zipcode formats Oney integration rules:
 * - ES: CCCCC
 * - PT: CCCC-CCC
 * - FR: CCCCC
 * - IT: CCCCC
 * - BE: CCCC
 */
abstract class OneyZipCodeValidator implements PaymentEligibilityValidatorInterface
{
    private string $code = 'oney';

    protected string $message = 'sylius_hipay_plugin.checkout.oney.zipcode_invalid';

    public function validate(?PaymentInterface $payment): ?PaymentEligibilityValidationResult
    {
        /** @var OrderInterface|null $order */
        $order = $payment?->getOrder();
        if (null === $order) {
            return null;
        }
        $countryCode = $this->getCountryCode($order);
        $zipCode = $this->getZipCode($order);
        if (null === $countryCode || null === $zipCode) {
            return null;
        }

        $valid = match ($countryCode) {
            'IT', 'FR', 'ES' => $this->matches($zipCode, '/^\d{5}$/'),
            'BE' => $this->matches($zipCode, '/^\d{4}$/'),
            'PT' => $this->matches($zipCode, '/^\d{4}-\d{3}$/'),
            default => true,
        };

        if ($valid) {
            return null;
        }

        return new PaymentEligibilityValidationResult(
            $this->message,
            ['%expected_format%' => $this->getExpectedZipFormat($countryCode)],
        );
    }

    protected function getExpectedZipFormat(string $countryCode): string
    {
        return match ($countryCode) {
            'IT', 'FR', 'ES' => 'CCCCC',
            'BE' => 'CCCC',
            'PT' => 'CCCC-CCC',
            default => '',
        };
    }

    protected function matches(string $value, string $pattern): bool
    {
        return 1 === preg_match($pattern, $value);
    }

    public function supports(string $paymentProduct): bool
    {
        return $paymentProduct === $this->code;
    }

    abstract public function getCountryCode(OrderInterface $order): ?string;

    abstract public function getZipCode(OrderInterface $order): ?string;
}
