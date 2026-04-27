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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\PaymentEligibility\Validator;

use HiPay\SyliusHiPayPlugin\PaymentEligibility\PaymentEligibilityValidationResult;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\OneyBillingPhoneValidator;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class OneyBillingPhoneValidatorTest extends TestCase
{
    private OneyBillingPhoneValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new OneyBillingPhoneValidator();
    }

    public function testSupportsOneyOnly(): void
    {
        $this->assertTrue($this->validator->supports('oney'));
        $this->assertFalse($this->validator->supports('3xcb'));
    }

    public function testValidateReturnsNullWhenPhoneOrCountryMissing(): void
    {
        $this->assertInstanceOf(PaymentEligibilityValidationResult::class, $this->validator->validate($this->createPaymentWithBilling('FR', null)));
        $this->assertInstanceOf(PaymentEligibilityValidationResult::class, $this->validator->validate($this->createPaymentWithBilling(null, '+33612345678')));
    }

    /**
     * @dataProvider validPhoneAsStoredProvider
     */
    public function testValidateReturnsNullWhenStoredPhoneMatchesPattern(string $country, string $phone): void
    {
        $payment = $this->createPaymentWithBilling($country, $phone);

        $this->assertNull($this->validator->validate($payment));
    }

    /**
     * Exact strings as stored on the address (must match {@see OneyPhoneValidator} regexes).
     *
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function validPhoneAsStoredProvider(): iterable
    {
        yield 'FR compact E.164' => ['FR', '+33612345678'];
        yield 'ES compact E.164' => ['ES', '+34612345678'];
        yield 'IT compact E.164' => ['IT', '+393123456789'];
        yield 'BE compact E.164' => ['BE', '+32412345678'];
        yield 'PT compact E.164' => ['PT', '+351912345678'];
        yield 'unsupported country skips validation' => ['DE', 'not-a-phone'];
    }

    public function testValidateReturnsResultWhenFrenchNumberInvalid(): void
    {
        $payment = $this->createPaymentWithBilling('FR', '12345');

        $result = $this->validator->validate($payment);

        $this->assertInstanceOf(PaymentEligibilityValidationResult::class, $result);
        $this->assertSame('sylius_hipay_plugin.checkout.oney.billing_phone_invalid', $result->message);
        $this->assertArrayHasKey('%expected_format%', $result->parameters);
        $this->assertIsString($result->parameters['%expected_format%']);
    }

    public function testValidateReturnsNullWhenFrenchPhoneHasSpacesRemovedToValidE164(): void
    {
        // Validator strips spaces before matching; result must match +33[67] + 8 digits.
        $this->assertNull(
            $this->validator->validate($this->createPaymentWithBilling('FR', '+33 6 12 34 56 78')),
        );
    }

    public function testValidateReturnsResultWhenPhoneMissingPlus(): void
    {
        $this->assertInstanceOf(
            PaymentEligibilityValidationResult::class,
            $this->validator->validate($this->createPaymentWithBilling('FR', '33612345678')),
        );
    }

    private function createPaymentWithBilling(?string $country, ?string $phone): PaymentInterface
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getCountryCode')->willReturn($country);
        $address->method('getPhoneNumber')->willReturn($phone);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getBillingAddress')->willReturn($address);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        return $payment;
    }
}
