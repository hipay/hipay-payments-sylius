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

use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\ApplePayConfigurationType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\ApplePayConfigurationDefaultsInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\ApplePayHandler;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;

final class ApplePayHandlerTest extends TestCase
{
    private ApplePayHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ApplePayHandler();
    }

    public function testGetCodeReturnsApplePay(): void
    {
        $this->assertSame('apple-pay', $this->handler->getCode());
    }

    public function testSupportsApplePayCode(): void
    {
        $this->assertTrue($this->handler->supports('apple-pay'));
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
        yield 'paypal' => ['paypal'];
        yield 'ideal' => ['ideal'];
        yield 'bancontact' => ['bancontact'];
    }

    public function testGetFormTypeReturnsApplePayConfigurationType(): void
    {
        $this->assertSame(ApplePayConfigurationType::class, $this->handler->getFormType());
    }

    public function testGetJsInitConfigContainsSdkProductOverride(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn(null);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertSame('paymentRequestButton', $config['_sdkProduct']);
    }

    public function testGetJsInitConfigBrowserCheckDependsOnMultiBrowserDefault(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn(null);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $expected = ApplePayConfigurationDefaultsInterface::MULTI_BROWSER ? '' : 'applePaySession';
        $this->assertSame($expected, $config['_browserCheck']);
    }

    public function testGetJsInitConfigReturnsDefaultsWithNoGatewayConfig(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn(null);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertSame('', $config['displayName']);
        $this->assertSame('buy', $config['applePayStyle']['type']);
        $this->assertSame('black', $config['applePayStyle']['color']);
        $this->assertSame([], $config['request']);
    }

    public function testGetJsInitConfigWithPaymentBuildsFullRequest(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn([
            'configuration' => [
                'display_name' => 'MyShop',
                'supported_networks' => ['visa', 'masterCard'],
                'button_type' => 'pay',
                'button_color' => 'white',
            ],
        ]);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $address = $this->createMock(AddressInterface::class);
        $address->method('getCountryCode')->willReturn('FR');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn('EUR');
        $order->method('getBillingAddress')->willReturn($address);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn(5000);
        $payment->method('getOrder')->willReturn($order);

        $config = $this->handler->getJsInitConfig($paymentMethod, $payment);

        $this->assertSame('paymentRequestButton', $config['_sdkProduct']);
        $this->assertSame('MyShop', $config['displayName']);
        $this->assertSame('pay', $config['applePayStyle']['type']);
        $this->assertSame('white', $config['applePayStyle']['color']);
        $this->assertSame('50', $config['request']['total']['amount']);
        $this->assertSame('MyShop', $config['request']['total']['label']);
        $this->assertSame('FR', $config['request']['countryCode']);
        $this->assertSame('EUR', $config['request']['currencyCode']);
        $this->assertSame(['visa', 'masterCard'], $config['request']['supportedNetworks']);
    }

    public function testGetJsInitConfigFallsBackToChannelNameForDisplayName(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn(null);

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getName')->willReturn('Test Channel');

        $address = $this->createMock(AddressInterface::class);
        $address->method('getCountryCode')->willReturn('DE');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getChannel')->willReturn($channel);
        $order->method('getCurrencyCode')->willReturn('EUR');
        $order->method('getBillingAddress')->willReturn($address);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn(1000);
        $payment->method('getOrder')->willReturn($order);

        $config = $this->handler->getJsInitConfig($paymentMethod, $payment);

        $this->assertSame('Test Channel', $config['displayName']);
        // total.label must also use the channel name fallback (HIPASYLU001-122)
        $this->assertSame('Test Channel', $config['request']['total']['label']);
    }

    public function testGetJsInitConfigTotalLabelFallsBackToTotalStringWhenDisplayNameAndChannelAreEmpty(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn([
            'configuration' => ['display_name' => ''],
        ]);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('getName')->willReturn('');

        $address = $this->createMock(AddressInterface::class);
        $address->method('getCountryCode')->willReturn('FR');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getChannel')->willReturn($channel);
        $order->method('getCurrencyCode')->willReturn('EUR');
        $order->method('getBillingAddress')->willReturn($address);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn(1000);
        $payment->method('getOrder')->willReturn($order);

        $config = $this->handler->getJsInitConfig($paymentMethod, $payment);

        // Last-resort fallback when both display_name and channel name are empty
        $this->assertSame('Total', $config['request']['total']['label']);
    }

    // --- multiBrowsers / displayMode ---

    public function testGetJsInitConfigContainsMultiBrowsersDefaultFalse(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn(null);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertArrayHasKey('multiBrowsers', $config);
        $this->assertFalse($config['multiBrowsers']);
    }

    public function testGetJsInitConfigMultiBrowserTrueEmptiesBrowserCheck(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn([
            'configuration' => ['multi_browser' => true],
        ]);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertTrue($config['multiBrowsers']);
        $this->assertSame('', $config['_browserCheck']);
    }

    public function testGetJsInitConfigMultiBrowserFalseKeepsBrowserCheck(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn([
            'configuration' => ['multi_browser' => false],
        ]);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertFalse($config['multiBrowsers']);
        $this->assertSame('applePaySession', $config['_browserCheck']);
    }

    public function testGetJsInitConfigContainsDisplayModeDefault(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn(null);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertArrayHasKey('displayMode', $config);
        $this->assertSame(ApplePayConfigurationDefaultsInterface::DISPLAY_MODE, $config['displayMode']);
    }

    public function testGetJsInitConfigDisplayModeCanBeOverridden(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn([
            'configuration' => ['display_mode' => 'modal'],
        ]);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertSame('modal', $config['displayMode']);
    }

    // --- onlyActiveCard + merchantIdentifier ---

    public function testGetJsInitConfigInjectsOnlyActiveCardWhenFlagAndIdentifierAreSet(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn([
            'configuration' => [
                'only_active_card' => true,
                'merchant_identifier' => 'merchant.com.example',
            ],
        ]);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertTrue($config['onlyActiveCard']);
        $this->assertSame('merchant.com.example', $config['merchantIdentifier']);
    }

    public function testGetJsInitConfigDoesNotInjectOnlyActiveCardWhenFlagIsFalse(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn([
            'configuration' => [
                'only_active_card' => false,
                'merchant_identifier' => 'merchant.com.example',
            ],
        ]);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertArrayNotHasKey('onlyActiveCard', $config);
        $this->assertArrayNotHasKey('merchantIdentifier', $config);
    }

    public function testGetJsInitConfigDoesNotInjectOnlyActiveCardWhenMerchantIdentifierIsEmpty(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn([
            'configuration' => [
                'only_active_card' => true,
                'merchant_identifier' => '',
            ],
        ]);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertArrayNotHasKey('onlyActiveCard', $config);
        $this->assertArrayNotHasKey('merchantIdentifier', $config);
    }
}
