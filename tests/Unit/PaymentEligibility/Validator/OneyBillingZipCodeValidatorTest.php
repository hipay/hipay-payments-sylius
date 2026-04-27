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
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\OneyBillingZipCodeValidator;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class OneyBillingZipCodeValidatorTest extends TestCase
{
    private OneyBillingZipCodeValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new OneyBillingZipCodeValidator();
    }

    public function testSupportsOneyOnly(): void
    {
        $this->assertTrue($this->validator->supports('oney'));
        $this->assertFalse($this->validator->supports('card'));
    }

    public function testValidateReturnsNullWhenPaymentIsNull(): void
    {
        $this->assertNull($this->validator->validate(null));
    }

    public function testValidateReturnsNullWhenOrderIsNull(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn(null);

        $this->assertNull($this->validator->validate($payment));
    }

    public function testValidateReturnsNullWhenBillingAddressIsNull(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getBillingAddress')->willReturn(null);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $this->assertNull($this->validator->validate($payment));
    }

    public function testValidateReturnsNullWhenCountryOrPostcodeMissing(): void
    {
        $payment = $this->createPaymentWithBilling('FR', null);
        $this->assertNull($this->validator->validate($payment));

        $payment = $this->createPaymentWithBilling(null, '75001');
        $this->assertNull($this->validator->validate($payment));
    }

    /**
     * @dataProvider validZipProvider
     */
    public function testValidateReturnsNullForValidZip(string $country, string $postcode): void
    {
        $payment = $this->createPaymentWithBilling($country, $postcode);

        $this->assertNull($this->validator->validate($payment));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function validZipProvider(): iterable
    {
        yield 'FR five digits' => ['FR', '75001'];
        yield 'BE four digits' => ['BE', '1000'];
        yield 'PT with dash' => ['PT', '1000-001'];
        yield 'unsupported country skips validation' => ['DE', 'invalid'];
    }

    /**
     * @dataProvider invalidZipProvider
     */
    public function testValidateReturnsResultWithExpectedFormatParameter(
        string $country,
        string $postcode,
        string $expectedFormat,
    ): void {
        $payment = $this->createPaymentWithBilling($country, $postcode);

        $result = $this->validator->validate($payment);

        $this->assertInstanceOf(PaymentEligibilityValidationResult::class, $result);
        $this->assertSame('sylius_hipay_plugin.checkout.oney.billing_zipcode_invalid', $result->message);
        $this->assertSame(['%expected_format%' => $expectedFormat], $result->parameters);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function invalidZipProvider(): iterable
    {
        yield 'FR too short' => ['FR', '7500', 'CCCCC'];
        yield 'BE too long' => ['BE', '10000', 'CCCC'];
        yield 'PT wrong pattern' => ['PT', '1000001', 'CCCC-CCC'];
    }

    private function createPaymentWithBilling(?string $country, ?string $postcode): PaymentInterface
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getCountryCode')->willReturn($country);
        $address->method('getPostcode')->willReturn($postcode);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getBillingAddress')->willReturn($address);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        return $payment;
    }
}
