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
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\PaypalShippingAddressValidator;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class PaypalShippingAddressValidatorTest extends TestCase
{
    private PaypalShippingAddressValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PaypalShippingAddressValidator();
    }

    public function testSupportsPaypalOnly(): void
    {
        $this->assertTrue($this->validator->supports('paypal'));
        $this->assertFalse($this->validator->supports('card'));
        $this->assertFalse($this->validator->supports('oney'));
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

    public function testValidateReturnsResultWhenShippingAddressIsNull(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn(null);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $result = $this->validator->validate($payment);

        $this->assertInstanceOf(PaymentEligibilityValidationResult::class, $result);
        $this->assertSame('sylius_hipay_plugin.checkout.paypal.shipping_address_incomplete', $result->message);
    }

    public function testValidateReturnsNullWhenAllFieldsArePresent(): void
    {
        $payment = $this->createPaymentWithShipping('123 Main St', 'Paris', '75001', 'FR');

        $this->assertNull($this->validator->validate($payment));
    }

    /**
     * @dataProvider missingFieldsProvider
     */
    public function testValidateReturnsResultWhenFieldIsMissing(
        ?string $street,
        ?string $city,
        ?string $postcode,
        ?string $country,
        string $expectedMissingField,
    ): void {
        $payment = $this->createPaymentWithShipping($street, $city, $postcode, $country);
        $result = $this->validator->validate($payment);

        $this->assertInstanceOf(PaymentEligibilityValidationResult::class, $result);
        $this->assertSame('sylius_hipay_plugin.checkout.paypal.shipping_address_incomplete', $result->message);
        $this->assertArrayHasKey('%fields%', $result->parameters);
        $this->assertStringContainsString($expectedMissingField, $result->parameters['%fields%']);
    }

    /** @return iterable<string, array{0: ?string, 1: ?string, 2: ?string, 3: ?string, 4: string}> */
    public static function missingFieldsProvider(): iterable
    {
        yield 'missing street' => [null, 'Paris', '75001', 'FR', 'streetaddress'];
        yield 'empty street' => ['', 'Paris', '75001', 'FR', 'streetaddress'];
        yield 'blank street' => ['   ', 'Paris', '75001', 'FR', 'streetaddress'];
        yield 'missing city' => ['123 Main St', null, '75001', 'FR', 'city'];
        yield 'empty city' => ['123 Main St', '', '75001', 'FR', 'city'];
        yield 'missing postcode' => ['123 Main St', 'Paris', null, 'FR', 'zipCode'];
        yield 'empty postcode' => ['123 Main St', 'Paris', '', 'FR', 'zipCode'];
        yield 'missing country' => ['123 Main St', 'Paris', '75001', null, 'country'];
        yield 'empty country' => ['123 Main St', 'Paris', '75001', '', 'country'];
    }

    public function testValidateReturnsAllMissingFieldsWhenMultipleAreMissing(): void
    {
        $payment = $this->createPaymentWithShipping(null, null, null, null);
        $result = $this->validator->validate($payment);

        $this->assertInstanceOf(PaymentEligibilityValidationResult::class, $result);
        $fields = $result->parameters['%fields%'];
        $this->assertStringContainsString('streetaddress', $fields);
        $this->assertStringContainsString('city', $fields);
        $this->assertStringContainsString('zipCode', $fields);
        $this->assertStringContainsString('country', $fields);
    }

    private function createPaymentWithShipping(
        ?string $street,
        ?string $city,
        ?string $postcode,
        ?string $country,
    ): PaymentInterface {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getStreet')->willReturn($street);
        $address->method('getCity')->willReturn($city);
        $address->method('getPostcode')->willReturn($postcode);
        $address->method('getCountryCode')->willReturn($country);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn($address);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        return $payment;
    }
}
