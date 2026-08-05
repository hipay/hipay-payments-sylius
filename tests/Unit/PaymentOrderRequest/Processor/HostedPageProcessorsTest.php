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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\PaymentOrderRequest\Processor;

use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\HostedPageProductsProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\HostedPageSettingsProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\PaypalConfigurationDefaultsInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final class HostedPageProcessorsTest extends TestCase
{
    public function testSettingsProcessorMapsHostedPageConfiguration(): void
    {
        $processor = new HostedPageSettingsProcessorPayment();
        $request = new HostedPaymentPageRequest();
        $context = $this->createContext([
            'hosted_page' => [
                'template' => 'iframe_js',
                'theme_code' => 'custom-theme',
                'display_cancel_button' => true,
                'time_limit_to_pay' => 7200,
            ],
        ]);

        $processor->process($request, $context);

        $this->assertSame('basic-js', $request->template);
        $this->assertSame('custom-theme', $request->theme_code);
        $this->assertSame(1, $request->display_selector);
        $this->assertTrue($request->display_cancel_button);
        $this->assertSame(7200, $request->time_limit_to_pay);
    }

    public function testSettingsProcessorIgnoresPlainOrderRequest(): void
    {
        $processor = new HostedPageSettingsProcessorPayment();
        $request = new OrderRequest();
        $context = $this->createContext([
            'hosted_page' => ['template' => 'iframe_js'],
        ]);

        $processor->process($request, $context);

        $this->assertNull($request->template ?? null);
    }

    public function testProductsProcessorMapsPaymentProductListAndPaypalOptions(): void
    {
        $processor = $this->createProcessor();
        $request = new HostedPaymentPageRequest();
        $context = $this->createContext([
            'product_configurations' => [
                'ideal' => [
                    'enabled' => true,
                    'position' => 0,
                    'configuration' => [],
                ],
                'paypal' => [
                    'enabled' => true,
                    'position' => 1,
                    'configuration' => [
                        'button_label' => 'buynow',
                        'button_shape' => 'rect',
                        'button_color' => 'black',
                        'button_height' => 40,
                        'can_pay_later' => false,
                    ],
                ],
            ],
        ]);

        $processor->process($request, $context);

        $this->assertSame('ideal,paypal', $request->payment_product_list);
        $this->assertFalse(isset($request->payment_product));
        $this->assertSame('buynow', $request->paypal_v2_label);
        $this->assertSame('rect', $request->paypal_v2_shape);
        $this->assertSame('black', $request->paypal_v2_color);
        $this->assertSame(40, $request->paypal_v2_height);
        $this->assertFalse($request->paypal_v2_bnpl);
    }

    public function testProductsProcessorSortsProductsByPosition(): void
    {
        $processor = $this->createProcessor();
        $request = new HostedPaymentPageRequest();
        $context = $this->createContext([
            'product_configurations' => [
                'paypal' => ['enabled' => true, 'position' => 2, 'configuration' => []],
                'ideal' => ['enabled' => true, 'position' => 0, 'configuration' => []],
                'alma-3x' => ['enabled' => true, 'position' => 1, 'configuration' => []],
            ],
        ]);

        $processor->process($request, $context);

        $this->assertSame('ideal,alma-3x,paypal', $request->payment_product_list);
    }

    public function testProductsProcessorSkipsDisabledProducts(): void
    {
        $processor = $this->createProcessor();
        $request = new HostedPaymentPageRequest();
        $context = $this->createContext([
            'product_configurations' => [
                'ideal' => ['enabled' => true, 'position' => 0, 'configuration' => []],
                'paypal' => ['enabled' => false, 'position' => 1, 'configuration' => []],
            ],
        ]);

        $processor->process($request, $context);

        $this->assertSame('ideal', $request->payment_product_list);
    }

    public function testProductsProcessorUsesPaypalDefaultsWhenPaypalIsSelected(): void
    {
        $processor = $this->createProcessor();
        $request = new HostedPaymentPageRequest();
        $context = $this->createContext([
            'product_configurations' => [
                'paypal' => ['enabled' => true, 'position' => 0, 'configuration' => []],
            ],
        ]);

        $processor->process($request, $context);

        $this->assertSame(PaypalConfigurationDefaultsInterface::BUTTON_LABEL, $request->paypal_v2_label);
        $this->assertSame(PaypalConfigurationDefaultsInterface::BUTTON_SHAPE, $request->paypal_v2_shape);
        $this->assertSame(PaypalConfigurationDefaultsInterface::BUTTON_COLOR, $request->paypal_v2_color);
        $this->assertSame(PaypalConfigurationDefaultsInterface::BUTTON_HEIGHT, $request->paypal_v2_height);
        $this->assertTrue($request->paypal_v2_bnpl);
    }

    public function testProductsProcessorThrowsWhenPaymentProductsAreMissing(): void
    {
        $processor = $this->createProcessor();
        $request = new HostedPaymentPageRequest();
        $context = $this->createContext([]);

        $this->expectException(InvalidArgumentException::class);

        $processor->process($request, $context);
    }

    public function testProductsProcessorThrowsWhenAllProductsAreDisabled(): void
    {
        $processor = $this->createProcessor();
        $request = new HostedPaymentPageRequest();
        $context = $this->createContext([
            'product_configurations' => [
                'ideal' => ['enabled' => false, 'position' => 0, 'configuration' => []],
                'paypal' => ['enabled' => false, 'position' => 1, 'configuration' => []],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        $processor->process($request, $context);
    }

    public function testProductsProcessorBackwardCompatWithLegacyPaymentProducts(): void
    {
        $processor = $this->createProcessor();
        $request = new HostedPaymentPageRequest();
        $context = $this->createContext([
            'payment_products' => ['card', 'paypal'],
        ]);

        $processor->process($request, $context);

        $this->assertSame('card,paypal', $request->payment_product_list);
    }

    public function testProductsProcessorFiltersProductsByAllowedCurrency(): void
    {
        $processor = $this->createProcessor();
        $request = new HostedPaymentPageRequest();
        $context = $this->createContext(
            gatewayConfig: [
                'product_configurations' => [
                    'paypal' => ['enabled' => true, 'position' => 0, 'configuration' => ['allowed_currencies' => ['EUR', 'USD']]],
                    'ideal' => ['enabled' => true, 'position' => 1, 'configuration' => ['allowed_currencies' => ['EUR']]],
                    'multibanco' => ['enabled' => true, 'position' => 2, 'configuration' => ['allowed_currencies' => ['GBP']]],
                ],
            ],
            currencyCode: 'EUR',
        );

        $processor->process($request, $context);

        $this->assertSame('paypal,ideal', $request->payment_product_list);
    }

    public function testProductsProcessorFiltersProductsByAllowedCountry(): void
    {
        $processor = $this->createProcessor();
        $request = new HostedPaymentPageRequest();
        $context = $this->createContext(
            gatewayConfig: [
                'product_configurations' => [
                    'paypal' => ['enabled' => true, 'position' => 0, 'configuration' => ['allowed_countries' => ['FR', 'DE']]],
                    'ideal' => ['enabled' => true, 'position' => 1, 'configuration' => ['allowed_countries' => ['NL']]],
                    'bancontact' => ['enabled' => true, 'position' => 2, 'configuration' => ['allowed_countries' => ['BE']]],
                ],
            ],
            countryCode: 'FR',
        );

        $processor->process($request, $context);

        $this->assertSame('paypal', $request->payment_product_list);
    }

    public function testProductsProcessorAllowsProductWithEmptyCountryList(): void
    {
        $processor = $this->createProcessor();
        $request = new HostedPaymentPageRequest();
        $context = $this->createContext(
            gatewayConfig: [
                'product_configurations' => [
                    'paypal' => ['enabled' => true, 'position' => 0, 'configuration' => ['allowed_countries' => []]],
                ],
            ],
            countryCode: 'JP',
        );

        $processor->process($request, $context);

        $this->assertSame('paypal', $request->payment_product_list);
    }

    public function testProductsProcessorAllowsProductWithEmptyCurrencyList(): void
    {
        $processor = $this->createProcessor();
        $request = new HostedPaymentPageRequest();
        $context = $this->createContext(
            gatewayConfig: [
                'product_configurations' => [
                    'paypal' => ['enabled' => true, 'position' => 0, 'configuration' => ['allowed_currencies' => []]],
                ],
            ],
            currencyCode: 'JPY',
        );

        $processor->process($request, $context);

        $this->assertSame('paypal', $request->payment_product_list);
    }

    public function testProductsProcessorFiltersProductsBelowMinimumAmount(): void
    {
        $processor = $this->createProcessor();
        $request = new HostedPaymentPageRequest();
        $context = $this->createContext(
            gatewayConfig: [
                'product_configurations' => [
                    'paypal' => ['enabled' => true, 'position' => 0, 'configuration' => ['minimum_amount' => 5000]],
                    'ideal' => ['enabled' => true, 'position' => 1, 'configuration' => ['minimum_amount' => 1000]],
                ],
            ],
            amount: 2000,
        );

        $processor->process($request, $context);

        $this->assertSame('ideal', $request->payment_product_list);
    }

    public function testProductsProcessorFiltersProductsAboveMaximumAmount(): void
    {
        $processor = $this->createProcessor();
        $request = new HostedPaymentPageRequest();
        $context = $this->createContext(
            gatewayConfig: [
                'product_configurations' => [
                    'paypal' => ['enabled' => true, 'position' => 0, 'configuration' => ['maximum_amount' => 10000]],
                    'ideal' => ['enabled' => true, 'position' => 1, 'configuration' => ['maximum_amount' => 50000]],
                ],
            ],
            amount: 20000,
        );

        $processor->process($request, $context);

        $this->assertSame('ideal', $request->payment_product_list);
    }

    public function testProductsProcessorAlwaysExcludesApplePay(): void
    {
        $processor = $this->createProcessor();
        $request = new HostedPaymentPageRequest();
        $context = $this->createContext([
            'product_configurations' => [
                'card' => ['enabled' => true, 'position' => 0, 'configuration' => []],
                'apple-pay' => ['enabled' => true, 'position' => 1, 'configuration' => []],
                'paypal' => ['enabled' => true, 'position' => 2, 'configuration' => []],
            ],
        ]);

        $processor->process($request, $context);

        $this->assertSame('card,paypal', $request->payment_product_list);
    }

    public function testProductsProcessorUsesStoredAlmaLimits(): void
    {
        $processor = $this->createProcessor();
        $request = new HostedPaymentPageRequest();
        // 8000 cents (80€). alma-3x stored min=5000c (50€) → eligible. alma-4x stored min=10000c (100€) → not eligible.
        $context = $this->createContext(
            gatewayConfig: [
                'product_configurations' => [
                    'alma-3x' => ['enabled' => true, 'position' => 0, 'configuration' => ['minimum_amount' => 5000, 'maximum_amount' => 200000]],
                    'alma-4x' => ['enabled' => true, 'position' => 1, 'configuration' => ['minimum_amount' => 10000, 'maximum_amount' => 300000]],
                ],
            ],
            amount: 8000,
        );

        $processor->process($request, $context);

        $this->assertSame('alma-3x', $request->payment_product_list);
    }

    /**
     * @param array<string, mixed> $gatewayConfig
     */
    private function createContext(
        array $gatewayConfig = [],
        string $currencyCode = 'EUR',
        string $countryCode = 'FR',
        int $amount = 10000,
        string $accountCode = 'test-account',
    ): PaymentOrderRequestContext {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getCountryCode')->willReturn($countryCode);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn($currencyCode);
        $order->method('getBillingAddress')->willReturn($address);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getAmount')->willReturn($amount);

        $account = $this->createMock(AccountInterface::class);
        $account->method('getCode')->willReturn($accountCode);

        return new PaymentOrderRequestContext(
            order: $order,
            payment: $payment,
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $account,
            paymentProduct: 'hosted_page',
            payload: [],
            gatewayConfig: $gatewayConfig,
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );
    }

    private function createProcessor(): HostedPageProductsProcessorPayment
    {
        return new HostedPageProductsProcessorPayment();
    }
}
