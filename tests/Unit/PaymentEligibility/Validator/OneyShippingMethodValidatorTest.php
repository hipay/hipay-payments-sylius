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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\PaymentEligibility\Validator;

use HiPay\SyliusHiPayPlugin\Entity\OneyShippingMethodInterface;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\PaymentEligibilityValidationResult;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\OneyShippingMethodValidator;
use HiPay\SyliusHiPayPlugin\Provider\OneyShippingMethodProviderInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class OneyShippingMethodValidatorTest extends TestCase
{
    public function testValidateReturnsNullWhenShippingMethodIsMapped(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $mapped = $this->createMock(OneyShippingMethodInterface::class);

        $provider = $this->createMock(OneyShippingMethodProviderInterface::class);
        $provider->method('getByOrder')->with($order)->willReturn($mapped);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $validator = new OneyShippingMethodValidator($provider);

        $this->assertNull($validator->validate($payment));
    }

    public function testValidateReturnsResultWhenShippingMethodIsNotMapped(): void
    {
        $order = $this->createMock(OrderInterface::class);

        $provider = $this->createMock(OneyShippingMethodProviderInterface::class);
        $provider->method('getByOrder')->with($order)->willReturn(null);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $validator = new OneyShippingMethodValidator($provider);
        $result = $validator->validate($payment);

        $this->assertInstanceOf(PaymentEligibilityValidationResult::class, $result);
        $this->assertSame('sylius_hipay_plugin.checkout.oney.shiping_method_mapping_invalid', $result->message);
        $this->assertSame([], $result->parameters);
    }
}
