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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Provider;

use HiPay\SyliusHiPayPlugin\Client\ClientProviderInterface;
use HiPay\SyliusHiPayPlugin\Client\HiPayClientInterface;
use HiPay\SyliusHiPayPlugin\Provider\AlmaLimitsProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AlmaLimitsProviderTest extends TestCase
{
    private ClientProviderInterface $clientProvider;

    private HiPayClientInterface $client;

    private AlmaLimitsProvider $provider;

    protected function setUp(): void
    {
        $this->client = $this->createMock(HiPayClientInterface::class);
        $this->clientProvider = $this->createMock(ClientProviderInterface::class);
        $this->clientProvider->method('getForAccountCode')->willReturn($this->client);
        $this->provider = new AlmaLimitsProvider($this->clientProvider);
    }

    public function testReturnsLimitsForAlma3x(): void
    {
        $this->client->method('getAlmaBasketAmounts')->willReturn([
            'basketAmountMin3x' => 30.0,
            'basketAmountMax3x' => 2000.0,
            'basketAmountMin4x' => 50.0,
            'basketAmountMax4x' => 2000.0,
        ]);

        $limits = $this->provider->getLimits('test_account', 'alma-3x');

        $this->assertSame(['min' => 30.0, 'max' => 2000.0], $limits);
    }

    public function testReturnsLimitsForAlma4x(): void
    {
        $this->client->method('getAlmaBasketAmounts')->willReturn([
            'basketAmountMin3x' => 30.0,
            'basketAmountMax3x' => 2000.0,
            'basketAmountMin4x' => 50.0,
            'basketAmountMax4x' => 2000.0,
        ]);

        $limits = $this->provider->getLimits('test_account', 'alma-4x');

        $this->assertSame(['min' => 50.0, 'max' => 2000.0], $limits);
    }

    public function testUsesCorrectAccountCode(): void
    {
        $this->clientProvider->expects($this->once())
            ->method('getForAccountCode')
            ->with('my_account_code')
            ->willReturn($this->client);

        $this->client->method('getAlmaBasketAmounts')->willReturn([
            'basketAmountMin3x' => 30.0,
            'basketAmountMax3x' => 2000.0,
        ]);

        $this->provider->getLimits('my_account_code', 'alma-3x');
    }

    public function testReturnsNullWhenApiReturnsNull(): void
    {
        $this->client->method('getAlmaBasketAmounts')->willReturn(null);

        $this->assertNull($this->provider->getLimits('test_account', 'alma-3x'));
    }

    public function testReturnsNullWhenKeysAreMissing(): void
    {
        $this->client->method('getAlmaBasketAmounts')->willReturn([
            'basketAmountMin3x' => 30.0,
            // basketAmountMax3x missing
        ]);

        $this->assertNull($this->provider->getLimits('test_account', 'alma-3x'));
    }

    public function testReturnsNullWhenApiThrows(): void
    {
        $this->client->method('getAlmaBasketAmounts')->willThrowException(new RuntimeException('API error'));

        $this->assertNull($this->provider->getLimits('test_account', 'alma-3x'));
    }

    public function testThrowsForUnsupportedProduct(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported Alma product "alma-12x"');

        $this->provider->getLimits('test_account', 'alma-12x');
    }
}
