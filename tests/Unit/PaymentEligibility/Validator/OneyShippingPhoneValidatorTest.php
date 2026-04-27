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
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\OneyShippingPhoneValidator;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class OneyShippingPhoneValidatorTest extends TestCase
{
    private OneyShippingPhoneValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new OneyShippingPhoneValidator();
    }

    public function testValidateUsesShippingAddress(): void
    {
        $shipping = $this->createMock(AddressInterface::class);
        $shipping->method('getCountryCode')->willReturn('FR');
        $shipping->method('getPhoneNumber')->willReturn('000');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn($shipping);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $result = $this->validator->validate($payment);

        $this->assertInstanceOf(PaymentEligibilityValidationResult::class, $result);
        $this->assertSame('sylius_hipay_plugin.checkout.oney.shipping_phone_invalid', $result->message);
    }

    public function testValidateReturnsNullWhenShippingAddressIsNull(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn(null);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $result = $this->validator->validate($payment);

        $this->assertInstanceOf(PaymentEligibilityValidationResult::class, $result);
        $this->assertSame('sylius_hipay_plugin.checkout.oney.shipping_phone_invalid', $result->message);
    }
}
