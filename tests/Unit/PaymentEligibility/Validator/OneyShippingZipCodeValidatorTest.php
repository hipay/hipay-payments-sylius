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
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\OneyShippingZipCodeValidator;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class OneyShippingZipCodeValidatorTest extends TestCase
{
    private OneyShippingZipCodeValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new OneyShippingZipCodeValidator();
    }

    public function testValidateUsesShippingAddress(): void
    {
        $shipping = $this->createMock(AddressInterface::class);
        $shipping->method('getCountryCode')->willReturn('FR');
        $shipping->method('getPostcode')->willReturn('bad');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn($shipping);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $result = $this->validator->validate($payment);

        $this->assertInstanceOf(PaymentEligibilityValidationResult::class, $result);
        $this->assertSame('sylius_hipay_plugin.checkout.oney.shipping_zipcode_invalid', $result->message);
    }

    public function testValidateReturnsNullWhenShippingAddressIsNull(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn(null);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $this->assertNull($this->validator->validate($payment));
    }

    private function createPaymentWithShipping(?string $country, ?string $postcode): PaymentInterface
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getCountryCode')->willReturn($country);
        $address->method('getPostcode')->willReturn($postcode);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn($address);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        return $payment;
    }

    public function testValidateReturnsNullForValidPortuguesePostcode(): void
    {
        $payment = $this->createPaymentWithShipping('PT', '1000-001');

        $this->assertNull($this->validator->validate($payment));
    }
}
