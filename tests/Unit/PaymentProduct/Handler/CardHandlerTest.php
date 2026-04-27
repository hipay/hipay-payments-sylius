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

use DateTimeImmutable;
use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\CardConfigurationType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\CardHandler;
use HiPay\SyliusHiPayPlugin\Repository\SavedCardRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Symfony\Component\Clock\MockClock;

final class CardHandlerTest extends TestCase
{
    private CardHandler $handler;

    protected function setUp(): void
    {
        $customerContext = $this->createMock(CustomerContextInterface::class);
        $savedCardRepository = $this->createMock(SavedCardRepositoryInterface::class);
        $clock = new MockClock(new DateTimeImmutable('2026-03-25'));
        $this->handler = new CardHandler($customerContext, $savedCardRepository, $clock);
    }

    /**
     * @dataProvider supportedCardCodesProvider
     */
    public function testSupportsCardCodes(string $code): void
    {
        $this->assertTrue($this->handler->supports($code));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function supportedCardCodesProvider(): iterable
    {
        yield 'card' => ['card'];
        yield 'visa' => ['visa'];
        yield 'mastercard' => ['mastercard'];
        yield 'cb' => ['cb'];
        yield 'maestro' => ['maestro'];
        yield 'american-express' => ['american-express'];
        yield 'bcmc' => ['bcmc'];
    }

    /**
     * @dataProvider unsupportedCodesProvider
     */
    public function testDoesNotSupportUnknownCode(string $code): void
    {
        $this->assertFalse($this->handler->supports($code));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedCodesProvider(): iterable
    {
        yield 'paypal' => ['paypal'];
        yield 'ideal' => ['ideal'];
        yield 'sdd' => ['sdd'];
    }

    public function testGetFormTypeReturnsCardConfigurationType(): void
    {
        $this->assertSame(CardConfigurationType::class, $this->handler->getFormType());
    }

    public function testGetJsInitConfigReturnsEmptyWhenNoConfiguration(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn([]);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertSame([], $config);
    }

    public function testGetJsInitConfigReturnsEmptyWhenNoGatewayConfig(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn(null);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertSame([], $config);
    }

    public function testGetJsInitConfigReturnsFullConfig(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn([
            'configuration' => [
                'text_color' => '#111111',
                'font_size' => '14px',
                'one_click_enabled' => true,
            ],
        ]);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertSame('auto', $config['template']);
        $this->assertTrue($config['one_click']['enabled']);
        $this->assertArrayHasKey('styles', $config);
        $this->assertSame('#111111', $config['styles']['base']['color']);
        $this->assertSame('14px', $config['styles']['base']['fontSize']);
    }

    public function testGetJsInitConfigIncludesBrandWhenSet(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn([
            'configuration' => [
                'allowed_brands' => ['visa', 'mastercard'],
            ],
        ]);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertArrayHasKey('brand', $config);
        $this->assertSame(['visa', 'mastercard'], $config['brand']);
    }

    public function testGetCodeReturnsCard(): void
    {
        $this->assertSame('card', $this->handler->getCode());
    }
}
