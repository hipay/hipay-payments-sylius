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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\PaymentOrderRequest;

use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestBuilderInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestBuilderRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PaymentOrderRequestBuilderRegistryTest extends TestCase
{
    public function testGetReturnsMatchingBuilder(): void
    {
        $cardBuilder = $this->createBuilderFor('card');
        $registry = new PaymentOrderRequestBuilderRegistry(['card' => $cardBuilder]);

        $result = $registry->get('card');

        $this->assertSame($cardBuilder, $result);
    }

    public function testGetReturnsFirstSupportingBuilder(): void
    {
        $cardBuilder = $this->createBuilderFor('card');
        $visaBuilder = $this->createBuilderFor('visa');

        $registry = new PaymentOrderRequestBuilderRegistry([
            'card' => $cardBuilder,
            'visa' => $visaBuilder,
        ]);

        $this->assertSame($cardBuilder, $registry->get('card'));
        $this->assertSame($visaBuilder, $registry->get('visa'));
    }

    public function testGetThrowsInvalidArgumentExceptionWhenNoBuildersMatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No PaymentOrderRequest found for payment product "paypal"');

        $cardBuilder = $this->createBuilderFor('card');
        $registry = new PaymentOrderRequestBuilderRegistry(['card' => $cardBuilder]);

        $registry->get('paypal');
    }

    public function testGetThrowsWithAvailableProductsInMessage(): void
    {
        $registry = new PaymentOrderRequestBuilderRegistry([
            'card' => $this->createBuilderFor('card'),
            'visa' => $this->createBuilderFor('visa'),
        ]);

        try {
            $registry->get('paypal');
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('card', $exception->getMessage());
            $this->assertStringContainsString('visa', $exception->getMessage());
        }
    }

    public function testGetThrowsWhenRegistryIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $registry = new PaymentOrderRequestBuilderRegistry([]);

        $registry->get('card');
    }

    public function testHasReturnsTrueForRegisteredProduct(): void
    {
        $registry = new PaymentOrderRequestBuilderRegistry([
            'card' => $this->createBuilderFor('card'),
        ]);

        $this->assertTrue($registry->has('card'));
    }

    public function testHasReturnsFalseForUnregisteredProduct(): void
    {
        $registry = new PaymentOrderRequestBuilderRegistry([
            'card' => $this->createBuilderFor('card'),
        ]);

        $this->assertFalse($registry->has('paypal'));
    }

    public function testHasReturnsFalseForEmptyRegistry(): void
    {
        $registry = new PaymentOrderRequestBuilderRegistry([]);

        $this->assertFalse($registry->has('card'));
    }

    private function createBuilderFor(string $paymentProduct): PaymentOrderRequestBuilderInterface
    {
        $builder = $this->createMock(PaymentOrderRequestBuilderInterface::class);
        $builder->method('supports')
            ->willReturnCallback(fn (string $product) => $product === $paymentProduct);

        return $builder;
    }
}
