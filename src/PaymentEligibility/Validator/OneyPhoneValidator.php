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
 * Phone formats expected by Oney (E.164 with leading +), per HiPay / Oney integration rules:
 * - ES: +346… or +347…
 * - PT: +351 9T … with T ∈ {1,2,3,6}
 * - FR: +336… or +337…
 * - IT: +393…
 * - BE: +324…
 */
abstract class OneyPhoneValidator implements PaymentEligibilityValidatorInterface
{
    private string $code = 'oney';

    protected string $message = 'sylius_hipay_plugin.checkout.oney.phone_invalid';

    public function validate(?PaymentInterface $payment): ?PaymentEligibilityValidationResult
    {
        /** @var OrderInterface|null $order */
        $order = $payment?->getOrder();
        if (null === $order) {
            return null;
        }
        $countryCode = $this->getCountryCode($order);
        $phone = $this->getPhoneNumber($order);
        if (null === $countryCode || null === $phone || '' === trim($phone)) {
            return new PaymentEligibilityValidationResult(
                $this->message,
                ['%expected_format%' => $this->getExpectedPhoneFormat($countryCode)],
            );
        }
        $phone = str_replace(' ', '', $phone);

        $valid = match ($countryCode) {
            'ES' => $this->matches($phone, '/^\+34[67]\d{8}$/'),
            'PT' => $this->matches($phone, '/^\+3519[1236]\d{7}$/'),
            'FR' => $this->matches($phone, '/^\+33[67]\d{8}$/'),
            'IT' => $this->matches($phone, '/^\+393\d{9}$/'),
            'BE' => $this->matches($phone, '/^\+324\d{8}$/'),
            default => true,
        };

        if ($valid) {
            return null;
        }

        return new PaymentEligibilityValidationResult(
            $this->message,
            ['%expected_format%' => $this->getExpectedPhoneFormat($countryCode)],
        );
    }

    protected function getExpectedPhoneFormat(?string $countryCode): string
    {
        return match ($countryCode) {
            'ES' => '+346CCCCCCCC / +347CCCCCCCC',
            'PT' => '+351 9T CCCCCCC (T ∈ {1, 2, 3, 6})',
            'FR' => '+336CCCCCCCC / +337CCCCCCCC',
            'IT' => '+393CCCCCCCCC',
            'BE' => '+324CCCCCCCC',
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

    abstract public function getPhoneNumber(OrderInterface $order): ?string;
}
