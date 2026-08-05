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

use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\AlmaConfigurationType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\AlmaHandler;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class AlmaHandlerTest extends TestCase
{
    private AlmaHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new AlmaHandler();
    }

    public function testGetCodeReturnsAlma(): void
    {
        $this->assertSame('alma', $this->handler->getCode());
    }

    /**
     * @dataProvider supportedCodesProvider
     */
    public function testSupportsAlmaCodes(string $code): void
    {
        $this->assertTrue($this->handler->supports($code));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function supportedCodesProvider(): iterable
    {
        yield 'alma-3x' => ['alma-3x'];
        yield 'alma-4x' => ['alma-4x'];
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
        yield 'alma' => ['alma'];
        yield 'card' => ['card'];
        yield 'paypal' => ['paypal'];
        yield 'ideal' => ['ideal'];
    }

    public function testSupportsDoesNotMutateStateAcrossCallsWhenCalledWithDifferentCodes(): void
    {
        // Regression: supports() must be side-effect-free on a shared singleton
        $this->assertTrue($this->handler->supports('alma-3x'));
        $this->assertFalse($this->handler->supports('card'));
        $this->assertTrue($this->handler->supports('alma-4x'));
        $this->assertFalse($this->handler->supports('paypal'));
    }

    public function testGetFormTypeReturnsAlmaConfigurationType(): void
    {
        $this->assertSame(AlmaConfigurationType::class, $this->handler->getFormType());
    }

    public function testGetJsInitConfigReturnsExpectedStructure(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertSame('auto', $config['template']);
        $this->assertSame([], $config['fields']);
        $this->assertSame([], $config['styles']);
    }

    public function testGetAvailableCountriesReturnsFrance(): void
    {
        $this->assertSame(['FR'], $this->handler->getAvailableCountries());
    }

    public function testGetAvailableCurrenciesReturnsEur(): void
    {
        $this->assertSame(['EUR'], $this->handler->getAvailableCurrencies());
    }
}
