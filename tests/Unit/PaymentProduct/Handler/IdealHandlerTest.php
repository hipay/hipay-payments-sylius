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

use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\IdealHandler;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class IdealHandlerTest extends TestCase
{
    private IdealHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new IdealHandler();
    }

    public function testGetCodeReturnsIdeal(): void
    {
        $this->assertSame('ideal', $this->handler->getCode());
    }

    public function testSupportsIdealCode(): void
    {
        $this->assertTrue($this->handler->supports('ideal'));
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
        yield 'bancontact' => ['bancontact'];
        yield 'paypal' => ['paypal'];
        yield 'mbway' => ['mbway'];
    }

    public function testGetFormTypeReturnsNull(): void
    {
        $this->assertNull($this->handler->getFormType());
    }

    public function testGetJsInitConfigReturnsExpectedStructure(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $config = $this->handler->getJsInitConfig($paymentMethod);

        $this->assertSame('auto', $config['template']);
        $this->assertSame([], $config['fields']);
        $this->assertSame([], $config['styles']);
    }

    public function testGetAvailableCountriesReturnsNetherlands(): void
    {
        $this->assertSame(['NL'], $this->handler->getAvailableCountries());
    }

    public function testGetAvailableCurrenciesReturnsEur(): void
    {
        $this->assertSame(['EUR'], $this->handler->getAvailableCurrencies());
    }
}
