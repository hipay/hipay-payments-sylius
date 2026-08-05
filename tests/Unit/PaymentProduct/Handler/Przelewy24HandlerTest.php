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

use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\Przelewy24Handler;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class Przelewy24HandlerTest extends TestCase
{
    private Przelewy24Handler $handler;

    protected function setUp(): void
    {
        $this->handler = new Przelewy24Handler();
    }

    public function testGetCodeReturnsPrzelewy24(): void
    {
        $this->assertSame('przelewy24', $this->handler->getCode());
    }

    public function testSupportsPrzelewy24Code(): void
    {
        $this->assertTrue($this->handler->supports('przelewy24'));
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
        yield 'paypal' => ['paypal'];
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

    public function testGetAvailableCountriesReturnsPoland(): void
    {
        $this->assertSame(['PL'], $this->handler->getAvailableCountries());
    }

    public function testGetAvailableCurrenciesReturnsPln(): void
    {
        $this->assertSame(['PLN'], $this->handler->getAvailableCurrencies());
    }
}
