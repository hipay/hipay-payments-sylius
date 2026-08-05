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

use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\MultibancoConfigurationType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Handler\MultibancoHandler;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class MultibancoHandlerTest extends TestCase
{
    private MultibancoHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new MultibancoHandler();
    }

    public function testGetCodeReturnsMultibanco(): void
    {
        $this->assertSame('multibanco', $this->handler->getCode());
    }

    public function testSupportsMultibancoCode(): void
    {
        $this->assertTrue($this->handler->supports('multibanco'));
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
        yield 'mbway' => ['mbway'];
        yield 'ideal' => ['ideal'];
        yield 'paypal' => ['paypal'];
    }

    public function testGetFormTypeReturnsMultibancoConfigurationType(): void
    {
        $this->assertSame(MultibancoConfigurationType::class, $this->handler->getFormType());
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

    public function testGetAvailableCurrenciesReturnsEur(): void
    {
        $this->assertSame(['EUR'], $this->handler->getAvailableCurrencies());
    }
}
