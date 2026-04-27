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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\PaymentEligibility;

use HiPay\SyliusHiPayPlugin\PaymentEligibility\PaymentEligibilityValidatorInterface;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\PaymentEligibilityValidatorRegistry;
use PHPUnit\Framework\TestCase;

final class PaymentEligibilityValidatorRegistryTest extends TestCase
{
    public function testGetReturnsOnlyValidatorsSupportingPaymentProduct(): void
    {
        $oneyA = $this->createMock(PaymentEligibilityValidatorInterface::class);
        $oneyA->method('supports')->with('oney')->willReturn(true);

        $card = $this->createMock(PaymentEligibilityValidatorInterface::class);
        $card->method('supports')->with('oney')->willReturn(false);

        $oneyB = $this->createMock(PaymentEligibilityValidatorInterface::class);
        $oneyB->method('supports')->with('oney')->willReturn(true);

        $registry = new PaymentEligibilityValidatorRegistry([$oneyA, $card, $oneyB]);

        $this->assertSame([$oneyA, $oneyB], $registry->get('oney'));
    }

    public function testGetReturnsEmptyArrayWhenNoMatch(): void
    {
        $registry = new PaymentEligibilityValidatorRegistry([
            $this->createValidatorSupporting('card'),
        ]);

        $this->assertSame([], $registry->get('oney'));
    }

    public function testGetPreservesOrder(): void
    {
        $first = $this->createValidatorSupporting('oney');
        $second = $this->createValidatorSupporting('oney');

        $registry = new PaymentEligibilityValidatorRegistry([$first, $second]);

        $this->assertSame([$first, $second], $registry->get('oney'));
    }

    private function createValidatorSupporting(string $code): PaymentEligibilityValidatorInterface
    {
        $validator = $this->createMock(PaymentEligibilityValidatorInterface::class);
        $validator->method('supports')->willReturnCallback(static fn (string $p): bool => $p === $code);

        return $validator;
    }
}
