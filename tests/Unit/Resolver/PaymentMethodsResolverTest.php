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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Resolver;

use HiPay\SyliusHiPayPlugin\Resolver\PaymentMethodsResolver;
use PHPUnit\Framework\TestCase;
use stdClass;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface as CorePaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;

final class PaymentMethodsResolverTest extends TestCase
{
    public function testSupportsDelegatesToDecorated(): void
    {
        $subject = $this->createMock(PaymentInterface::class);

        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->expects($this->once())
            ->method('supports')
            ->with($subject)
            ->willReturn(true);

        $resolver = new PaymentMethodsResolver($decorated);

        $this->assertTrue($resolver->supports($subject));
    }

    public function testGetSupportedMethodsReturnsDecoratedResultWhenSubjectIsNotCorePayment(): void
    {
        $subject = $this->createMock(PaymentInterface::class);
        $methods = [new stdClass()];

        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->method('getSupportedMethods')->with($subject)->willReturn($methods);

        $resolver = new PaymentMethodsResolver($decorated);

        $this->assertSame($methods, $resolver->getSupportedMethods($subject));
    }

    public function testGetSupportedMethodsReturnsDecoratedResultWhenOrderIsNull(): void
    {
        $subject = $this->createMock(CorePaymentInterface::class);
        $subject->method('getOrder')->willReturn(null);
        $methods = [$this->createMock(PaymentMethodInterface::class)];

        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->method('getSupportedMethods')->with($subject)->willReturn($methods);

        $resolver = new PaymentMethodsResolver($decorated);

        $this->assertSame($methods, $resolver->getSupportedMethods($subject));
    }

    public function testNonHiPayMethodsPassThroughUnfiltered(): void
    {
        $method = $this->createPaymentMethodWithGateway('other_factory', []);

        $subject = $this->createCorePaymentWithOrder('USD', 'US');
        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->method('getSupportedMethods')->willReturn([$method]);

        $resolver = new PaymentMethodsResolver($decorated);
        $result = $resolver->getSupportedMethods($subject);

        $this->assertCount(1, $result);
        $this->assertSame($method, $result[0]);
    }

    public function testHiPayMethodWithoutConfigurationPassesThrough(): void
    {
        $method = $this->createPaymentMethodWithGateway('hipay_hosted_fields', []);

        $subject = $this->createCorePaymentWithOrder('EUR', 'FR');
        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->method('getSupportedMethods')->willReturn([$method]);

        $resolver = new PaymentMethodsResolver($decorated);
        $result = $resolver->getSupportedMethods($subject);

        $this->assertCount(1, $result);
    }

    public function testHiPayMethodExcludedWhenCountryNotAllowed(): void
    {
        $method = $this->createPaymentMethodWithGateway('hipay_hosted_fields', [
            'configuration' => [
                'allowed_countries' => ['DE', 'AT'],
            ],
        ]);

        $subject = $this->createCorePaymentWithOrder('EUR', 'FR');
        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->method('getSupportedMethods')->willReturn([$method]);

        $resolver = new PaymentMethodsResolver($decorated);
        $result = $resolver->getSupportedMethods($subject);

        $this->assertSame([], $result);
    }

    public function testHiPayMethodIncludedWhenCountryAllowed(): void
    {
        $method = $this->createPaymentMethodWithGateway('hipay_hosted_fields', [
            'configuration' => [
                'allowed_countries' => ['FR', 'DE'],
            ],
        ]);

        $subject = $this->createCorePaymentWithOrder('EUR', 'FR');
        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->method('getSupportedMethods')->willReturn([$method]);

        $resolver = new PaymentMethodsResolver($decorated);
        $result = $resolver->getSupportedMethods($subject);

        $this->assertCount(1, $result);
    }

    public function testHiPayMethodExcludedWhenCurrencyNotAllowed(): void
    {
        $method = $this->createPaymentMethodWithGateway('hipay_hosted_fields', [
            'configuration' => [
                'allowed_currencies' => ['USD', 'GBP'],
            ],
        ]);

        $subject = $this->createCorePaymentWithOrder('EUR', 'US');
        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->method('getSupportedMethods')->willReturn([$method]);

        $resolver = new PaymentMethodsResolver($decorated);
        $result = $resolver->getSupportedMethods($subject);

        $this->assertSame([], $result);
    }

    public function testHiPayMethodIncludedWhenCurrencyAllowed(): void
    {
        $method = $this->createPaymentMethodWithGateway('hipay_hosted_fields', [
            'configuration' => [
                'allowed_currencies' => ['EUR', 'USD'],
            ],
        ]);

        $subject = $this->createCorePaymentWithOrder('EUR', 'FR');
        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->method('getSupportedMethods')->willReturn([$method]);

        $resolver = new PaymentMethodsResolver($decorated);
        $result = $resolver->getSupportedMethods($subject);

        $this->assertCount(1, $result);
    }

    public function testHiPayMethodExcludedWhenBillingCountryNullButCountriesRestricted(): void
    {
        $method = $this->createPaymentMethodWithGateway('hipay_hosted_fields', [
            'configuration' => [
                'allowed_countries' => ['FR'],
            ],
        ]);

        $subject = $this->createCorePaymentWithOrder('EUR', null);
        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->method('getSupportedMethods')->willReturn([$method]);

        $resolver = new PaymentMethodsResolver($decorated);
        $result = $resolver->getSupportedMethods($subject);

        $this->assertSame([], $result);
    }

    public function testEmptyAllowedListsDoNotFilter(): void
    {
        $method = $this->createPaymentMethodWithGateway('hipay_hosted_fields', [
            'configuration' => [
                'allowed_countries' => [],
                'allowed_currencies' => [],
            ],
        ]);

        $subject = $this->createCorePaymentWithOrder('XYZ', 'ZZ');
        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->method('getSupportedMethods')->willReturn([$method]);

        $resolver = new PaymentMethodsResolver($decorated);
        $result = $resolver->getSupportedMethods($subject);

        $this->assertCount(1, $result);
    }

    public function testHiPayMethodExcludedWhenOrderTotalBelowMinimumAmount(): void
    {
        $method = $this->createPaymentMethodWithGateway('hipay_hosted_fields', [
            'configuration' => [
                'minimum_amount' => 5000,
            ],
        ]);

        $subject = $this->createCorePaymentWithOrder('EUR', 'FR', 2000);
        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->method('getSupportedMethods')->willReturn([$method]);

        $resolver = new PaymentMethodsResolver($decorated);
        $result = $resolver->getSupportedMethods($subject);

        $this->assertSame([], $result);
    }

    public function testHiPayMethodIncludedWhenOrderTotalAboveMinimumAmount(): void
    {
        $method = $this->createPaymentMethodWithGateway('hipay_hosted_fields', [
            'configuration' => [
                'minimum_amount' => 1000,
            ],
        ]);

        $subject = $this->createCorePaymentWithOrder('EUR', 'FR', 5000);
        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->method('getSupportedMethods')->willReturn([$method]);

        $resolver = new PaymentMethodsResolver($decorated);
        $result = $resolver->getSupportedMethods($subject);

        $this->assertCount(1, $result);
    }

    public function testHiPayMethodExcludedWhenOrderTotalAboveMaximumAmount(): void
    {
        $method = $this->createPaymentMethodWithGateway('hipay_hosted_fields', [
            'configuration' => [
                'maximum_amount' => 10000,
            ],
        ]);

        $subject = $this->createCorePaymentWithOrder('EUR', 'FR', 20000);
        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->method('getSupportedMethods')->willReturn([$method]);

        $resolver = new PaymentMethodsResolver($decorated);
        $result = $resolver->getSupportedMethods($subject);

        $this->assertSame([], $result);
    }

    public function testHiPayMethodIncludedWhenOrderTotalBelowMaximumAmount(): void
    {
        $method = $this->createPaymentMethodWithGateway('hipay_hosted_fields', [
            'configuration' => [
                'maximum_amount' => 50000,
            ],
        ]);

        $subject = $this->createCorePaymentWithOrder('EUR', 'FR', 10000);
        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->method('getSupportedMethods')->willReturn([$method]);

        $resolver = new PaymentMethodsResolver($decorated);
        $result = $resolver->getSupportedMethods($subject);

        $this->assertCount(1, $result);
    }

    public function testHiPayMethodIncludedWhenOrderTotalWithinMinMaxRange(): void
    {
        $method = $this->createPaymentMethodWithGateway('hipay_hosted_fields', [
            'configuration' => [
                'minimum_amount' => 1000,
                'maximum_amount' => 50000,
            ],
        ]);

        $subject = $this->createCorePaymentWithOrder('EUR', 'FR', 15000);
        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->method('getSupportedMethods')->willReturn([$method]);

        $resolver = new PaymentMethodsResolver($decorated);
        $result = $resolver->getSupportedMethods($subject);

        $this->assertCount(1, $result);
    }

    public function testHiPayMethodExcludedWhenOrderTotalOutsideMinMaxRange(): void
    {
        $method = $this->createPaymentMethodWithGateway('hipay_hosted_fields', [
            'configuration' => [
                'minimum_amount' => 5000,
                'maximum_amount' => 50000,
            ],
        ]);

        $subject = $this->createCorePaymentWithOrder('EUR', 'FR', 2000);
        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->method('getSupportedMethods')->willReturn([$method]);

        $resolver = new PaymentMethodsResolver($decorated);
        $result = $resolver->getSupportedMethods($subject);

        $this->assertSame([], $result);
    }

    public function testNullAmountLimitsDoNotFilter(): void
    {
        $method = $this->createPaymentMethodWithGateway('hipay_hosted_fields', [
            'configuration' => [
                'minimum_amount' => null,
                'maximum_amount' => null,
            ],
        ]);

        $subject = $this->createCorePaymentWithOrder('EUR', 'FR', 100);
        $decorated = $this->createMock(PaymentMethodsResolverInterface::class);
        $decorated->method('getSupportedMethods')->willReturn([$method]);

        $resolver = new PaymentMethodsResolver($decorated);
        $result = $resolver->getSupportedMethods($subject);

        $this->assertCount(1, $result);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createPaymentMethodWithGateway(string $factoryName, array $config): PaymentMethodInterface
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);
        $gatewayConfig->method('getConfig')->willReturn($config);

        $method = $this->createMock(PaymentMethodInterface::class);
        $method->method('getGatewayConfig')->willReturn($gatewayConfig);

        return $method;
    }

    private function createCorePaymentWithOrder(string $currencyCode, ?string $countryCode, int $orderTotal = 10000): CorePaymentInterface
    {
        $address = null;
        if (null !== $countryCode) {
            $address = $this->createMock(AddressInterface::class);
            $address->method('getCountryCode')->willReturn($countryCode);
        }

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCurrencyCode')->willReturn($currencyCode);
        $order->method('getBillingAddress')->willReturn($address);
        $order->method('getTotal')->willReturn($orderTotal);

        $payment = $this->createMock(CorePaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        return $payment;
    }
}
