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

use Doctrine\Common\Collections\ArrayCollection;
use HiPay\SyliusHiPayPlugin\Entity\OneyCategoryInterface;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\PaymentEligibilityValidationResult;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator\OneyCategoryValidator;
use HiPay\SyliusHiPayPlugin\Provider\OneyCategoryProviderInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ProductInterface;

final class OneyCategoryValidatorTest extends TestCase
{
    public function testValidateReturnsNullWhenEveryProductHasOneyCategory(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $oneyCategory = $this->createMock(OneyCategoryInterface::class);

        $provider = $this->createMock(OneyCategoryProviderInterface::class);
        $provider->method('getByProduct')->with($product)->willReturn($oneyCategory);

        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getProduct')->willReturn($product);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn(new ArrayCollection([$item]));

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $validator = new OneyCategoryValidator($provider);

        $this->assertNull($validator->validate($payment));
    }

    public function testValidateReturnsResultWhenAtLeastOneProductHasNoOneyCategory(): void
    {
        $productMapped = $this->createMock(ProductInterface::class);
        $productUnmapped = $this->createMock(ProductInterface::class);

        $provider = $this->createMock(OneyCategoryProviderInterface::class);
        $provider->method('getByProduct')->willReturnMap([
            [$productMapped, $this->createMock(OneyCategoryInterface::class)],
            [$productUnmapped, null],
        ]);

        $itemMapped = $this->createMock(OrderItemInterface::class);
        $itemMapped->method('getProduct')->willReturn($productMapped);

        $itemUnmapped = $this->createMock(OrderItemInterface::class);
        $itemUnmapped->method('getProduct')->willReturn($productUnmapped);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn(new ArrayCollection([$itemMapped, $itemUnmapped]));

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getOrder')->willReturn($order);

        $validator = new OneyCategoryValidator($provider);
        $result = $validator->validate($payment);

        $this->assertInstanceOf(PaymentEligibilityValidationResult::class, $result);
        $this->assertSame('sylius_hipay_plugin.checkout.oney.category_mapping_invalid', $result->message);
        $this->assertSame([], $result->parameters);
    }
}
