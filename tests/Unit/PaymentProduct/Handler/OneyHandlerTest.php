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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\PaymentProduct\Handler;

use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\GeneralConfigurationType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\OneyHandler;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class OneyHandlerTest extends TestCase
{
    private OneyHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new OneyHandler();
    }

    public function testGetCodeReturnsOney(): void
    {
        $this->assertSame('oney', $this->handler->getCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function oneyCodesProvider(): iterable
    {
        yield '3xcb' => ['3xcb'];
        yield '3xcb-no-fees' => ['3xcb-no-fees'];
        yield '4xcb' => ['4xcb'];
        yield '4xcb-no-fees' => ['4xcb-no-fees'];
        yield 'credit-long' => ['credit-long'];
    }

    public function testDoesNotSupportUnrelatedCodes(): void
    {
        $this->assertFalse($this->handler->supports('card'));
        $this->assertFalse($this->handler->supports('paypal'));
    }

    public function testGetFormTypeReturnsGeneralConfigurationType(): void
    {
        // paymentProduct is null by default → non-credit-long branch
        $this->assertSame(GeneralConfigurationType::class, $this->handler->getFormType());
    }

    public function testGetJsInitConfigWithoutPaymentReturnsEmptyRequest(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertSame('auto', $config['template']);
        $this->assertSame([], $config['fields']);
        $this->assertSame([], $config['styles']);
        $this->assertSame([], $config['request']);
    }

    public function testGetJsInitConfigWithPaymentBuildsRequest(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('EUR');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn(30000);
        $payment->method('getOrder')->willReturn($order);

        $config = $this->handler->getJsInitConfig($paymentMethod, $payment);

        $this->assertSame('300', $config['request']['amount']);
        $this->assertSame('EUR', $config['request']['currency']);
    }

    public function testGetJsInitConfigWithNullAmountFallsBackToZero(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn(null);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn(null);
        $payment->method('getOrder')->willReturn($order);

        $config = $this->handler->getJsInitConfig($paymentMethod, $payment);

        $this->assertSame('0', $config['request']['amount']);
        $this->assertSame('EUR', $config['request']['currency']);
    }

    public function testGetAvailableCountriesReturnsBaseList(): void
    {
        // paymentProduct is null → non-credit-long → includes BE
        $countries = $this->handler->getAvailableCountries();

        $this->assertContains('FR', $countries);
        $this->assertContains('IT', $countries);
        $this->assertContains('PT', $countries);
        $this->assertContains('ES', $countries);
        $this->assertContains('BE', $countries);
    }

    public function testGetAvailableCurrenciesReturnsEur(): void
    {
        $this->assertSame(['EUR'], $this->handler->getAvailableCurrencies());
    }
}
