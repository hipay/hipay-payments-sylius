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

use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\MbWayHandler;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class MbWayHandlerTest extends TestCase
{
    private MbWayHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new MbWayHandler();
    }

    public function testGetCodeReturnsMbway(): void
    {
        $this->assertSame('mbway', $this->handler->getCode());
    }

    public function testSupportsMbwayCode(): void
    {
        $this->assertTrue($this->handler->supports('mbway'));
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
        yield 'multibanco' => ['multibanco'];
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

    public function testGetAvailableCountriesReturnsPortugal(): void
    {
        $this->assertSame(['PT'], $this->handler->getAvailableCountries());
    }

    public function testGetAvailableCurrenciesReturnsEmpty(): void
    {
        $this->assertSame([], $this->handler->getAvailableCurrencies());
    }
}
