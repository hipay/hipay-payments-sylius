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
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\PaypalCurrencyValidator;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class PaypalCurrencyValidatorTest extends TestCase
{
    private PaypalCurrencyValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PaypalCurrencyValidator();
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

    public function testValidateReturnsNullWhenCurrencyCodeIsSet(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('EUR');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $this->assertNull($this->validator->validate($payment));
    }

    /**
     * @dataProvider validCurrencyProvider
     */
    public function testValidateReturnsNullForVariousCurrencies(string $currencyCode): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn($currencyCode);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $this->assertNull($this->validator->validate($payment));
    }

    /** @return iterable<string, array{0: string}> */
    public static function validCurrencyProvider(): iterable
    {
        yield 'EUR' => ['EUR'];
        yield 'USD' => ['USD'];
        yield 'GBP' => ['GBP'];
    }

    public function testValidateReturnsResultWhenCurrencyCodeIsNull(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn(null);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $result = $this->validator->validate($payment);

        $this->assertInstanceOf(PaymentEligibilityValidationResult::class, $result);
        $this->assertSame('sylius_hipay_plugin.checkout.paypal.currency_invalid', $result->message);
    }

    public function testValidateReturnsResultWhenCurrencyCodeIsEmpty(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $result = $this->validator->validate($payment);

        $this->assertInstanceOf(PaymentEligibilityValidationResult::class, $result);
        $this->assertSame('sylius_hipay_plugin.checkout.paypal.currency_invalid', $result->message);
    }
}
