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

use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\PaypalConfigurationType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\PaypalConfigurationDefaultsInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\PaypalHandler;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;

final class PaypalHandlerTest extends TestCase
{
    private PaypalHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new PaypalHandler();
    }

    public function testGetCodeReturnsPaypal(): void
    {
        $this->assertSame('paypal', $this->handler->getCode());
    }

    public function testSupportsPaypalCode(): void
    {
        $this->assertTrue($this->handler->supports('paypal'));
    }

    /**
     * @dataProvider unsupportedCodesProvider
     */
    public function testDoesNotSupportOtherCodes(string $code): void
    {
        $this->assertFalse($this->handler->supports($code));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedCodesProvider(): iterable
    {
        yield 'card' => ['card'];
        yield 'ideal' => ['ideal'];
        yield 'bancontact' => ['bancontact'];
        yield 'apple-pay' => ['apple-pay'];
    }

    public function testGetFormTypeReturnsPaypalConfigurationType(): void
    {
        $this->assertSame(PaypalConfigurationType::class, $this->handler->getFormType());
    }

    public function testGetJsInitConfigReturnsDefaultsWithNoGatewayConfig(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn(null);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertSame('auto', $config['template']);
        $this->assertTrue($config['canPayLater']);
        $this->assertSame(PaypalConfigurationDefaultsInterface::BUTTON_SHAPE, $config['paypalButtonStyle']['shape']);
        $this->assertSame(PaypalConfigurationDefaultsInterface::BUTTON_COLOR, $config['paypalButtonStyle']['color']);
        $this->assertSame(PaypalConfigurationDefaultsInterface::BUTTON_LABEL, $config['paypalButtonStyle']['label']);
        $this->assertSame(PaypalConfigurationDefaultsInterface::BUTTON_HEIGHT, $config['paypalButtonStyle']['height']);
        $this->assertSame([], $config['request']);
    }

    public function testGetJsInitConfigWithGatewayConfigAppliesValues(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn([
            'configuration' => [
                'can_pay_later' => false,
                'button_shape' => 'rect',
                'button_color' => 'blue',
                'button_label' => 'checkout',
                'button_height' => 55,
            ],
        ]);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertFalse($config['canPayLater']);
        $this->assertSame('rect', $config['paypalButtonStyle']['shape']);
        $this->assertSame('blue', $config['paypalButtonStyle']['color']);
        $this->assertSame('checkout', $config['paypalButtonStyle']['label']);
        $this->assertSame(55, $config['paypalButtonStyle']['height']);
    }

    public function testGetJsInitConfigWithPaymentBuildsFullRequest(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn(null);

        $address = $this->createMock(AddressInterface::class);
        $address->method('getPostcode')->willReturn('75000');
        $address->method('getCity')->willReturn('Paris');
        $address->method('getCountryCode')->willReturn('FR');
        $address->method('getStreet')->willReturn('1 Rue de Rivoli');
        $address->method('getFirstName')->willReturn('Jean');
        $address->method('getLastName')->willReturn('Dupont');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('EUR');
        $order->method('getShippingAddress')->willReturn($address);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn(10000);
        $payment->method('getOrder')->willReturn($order);

        $config = $this->handler->getJsInitConfig($paymentMethod, $payment);

        $this->assertSame('100', $config['request']['amount']);
        $this->assertSame('EUR', $config['request']['currency']);
        $this->assertSame('SHIPPING', $config['request']['customerShippingInformation']['shippingType']);
        $this->assertSame('75000', $config['request']['customerShippingInformation']['zipCode']);
        $this->assertSame('Paris', $config['request']['customerShippingInformation']['city']);
        $this->assertSame('FR', $config['request']['customerShippingInformation']['country']);
        $this->assertSame('1 Rue de Rivoli', $config['request']['customerShippingInformation']['streetaddress']);
        $this->assertSame('Jean', $config['request']['customerShippingInformation']['firstname']);
        $this->assertSame('Dupont', $config['request']['customerShippingInformation']['lastname']);
    }

    public function testGetJsInitConfigWithPaymentAndNoShippingAddressHasEmptyShippingInfo(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn(null);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('EUR');
        $order->method('getShippingAddress')->willReturn(null);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn(5000);
        $payment->method('getOrder')->willReturn($order);

        $config = $this->handler->getJsInitConfig($paymentMethod, $payment);

        $this->assertSame('50', $config['request']['amount']);
        $this->assertSame([], $config['request']['customerShippingInformation']);
    }

    public function testGetJsInitConfigWithNullAmountFallsBackToZero(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn(null);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn(null);
        $order->method('getShippingAddress')->willReturn(null);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn(null);
        $payment->method('getOrder')->willReturn($order);

        $config = $this->handler->getJsInitConfig($paymentMethod, $payment);

        $this->assertSame('0', $config['request']['amount']);
        $this->assertSame('EUR', $config['request']['currency']);
    }
}
