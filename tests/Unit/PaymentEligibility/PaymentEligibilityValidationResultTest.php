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

use HiPay\SyliusHiPayPlugin\PaymentEligibility\PaymentEligibilityValidationResult;
use PHPUnit\Framework\TestCase;

final class PaymentEligibilityValidationResultTest extends TestCase
{
    public function testHoldsMessageAndParameters(): void
    {
        $result = new PaymentEligibilityValidationResult(
            'my.translation.key',
            ['%expected_format%' => 'CCCCC'],
        );

        $this->assertSame('my.translation.key', $result->message);
        $this->assertSame(['%expected_format%' => 'CCCCC'], $result->parameters);
    }

    public function testAllowsEmptyParameters(): void
    {
        $result = new PaymentEligibilityValidationResult('key');

        $this->assertSame('key', $result->message);
        $this->assertSame([], $result->parameters);
    }
}
