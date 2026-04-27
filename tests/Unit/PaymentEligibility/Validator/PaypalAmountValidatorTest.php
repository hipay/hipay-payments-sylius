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
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\PaypalAmountValidator;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;

final class PaypalAmountValidatorTest extends TestCase
{
    private PaypalAmountValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PaypalAmountValidator();
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

    public function testValidateReturnsNullWhenAmountIsPositive(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn(5000);

        $this->assertNull($this->validator->validate($payment));
    }

    public function testValidateReturnsResultWhenAmountIsNull(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn(null);

        $result = $this->validator->validate($payment);

        $this->assertInstanceOf(PaymentEligibilityValidationResult::class, $result);
        $this->assertSame('sylius_hipay_plugin.checkout.paypal.amount_invalid', $result->message);
    }

    public function testValidateReturnsResultWhenAmountIsZero(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn(0);

        $result = $this->validator->validate($payment);

        $this->assertInstanceOf(PaymentEligibilityValidationResult::class, $result);
        $this->assertSame('sylius_hipay_plugin.checkout.paypal.amount_invalid', $result->message);
    }

    public function testValidateReturnsResultWhenAmountIsNegative(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn(-100);

        $result = $this->validator->validate($payment);

        $this->assertInstanceOf(PaymentEligibilityValidationResult::class, $result);
        $this->assertSame('sylius_hipay_plugin.checkout.paypal.amount_invalid', $result->message);
    }
}
