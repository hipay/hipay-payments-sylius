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

namespace Tests\HiPay\SyliusHiPayPlugin\Integration\PaymentOrderRequest\Processor;

use HiPay\Fullservice\Gateway\Model\Cart\Cart;
use HiPay\Fullservice\Gateway\Model\Cart\Item;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Entity\OneyCategoryInterface;
use HiPay\SyliusHiPayPlugin\Entity\OneyShippingMethodInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\OneyPaymentMethodProcessorPayment;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Oney\OneyStandardCategory;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Oney\OneyStandardShippingMethod;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PluginPaymentProduct;
use HiPay\SyliusHiPayPlugin\Provider\OneyCategoryProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\OneyShippingMethodProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

/**
 * HIPASYLU005-8: Oney 3x/4x requires a basket whose product lines are all categorized. The
 * basket is built as in production (basket processor, then Oney processor) on a tax-excluded
 * cart whose line total does not divide evenly by the quantity (rounding remainder): the
 * product is still sent as a single line, and that line must carry the Oney mandatory data.
 */
final class BasketProcessorPaymentOneyTest extends BasketTestCase
{
    private const HOSTED_PAGE = 'hosted_page';

    /**
     * @dataProvider oneyPaymentProductProvider
     *
     * @param array<string, mixed> $gatewayConfig
     */
    public function testOneyMandatoryInformationIsKeptOnEveryLine(string $paymentProduct, array $gatewayConfig): void
    {
        $taxCategory = $this->createTaxCategory('vat_20', 0.2, false);
        $variant = $this->createVariant('TB01378', 242, $taxCategory, shippingRequired: true);
        $this->createShippingMethod(500, $taxCategory);
        $order = $this->createOrder([[$variant, 3]]);

        $request = new OrderRequest();
        if (self::HOSTED_PAGE === $paymentProduct) {
            $request = new HostedPaymentPageRequest();
            $request->payment_product_list = implode(',', [
                PluginPaymentProduct::ONEY_3X->value,
                PluginPaymentProduct::ONEY_CREDIT_LONG->value,
            ]);
        }

        $this->processBasket($request, $order);
        $this->createOneyProcessor()->process($request, $this->createOneyContext($order, $paymentProduct, $gatewayConfig));

        self::assertInstanceOf(Cart::class, $request->basket);
        $items = array_values($request->basket->getAllItems());
        $goods = $this->goods($items);

        $this->assertBasketLinesAreConsistent($items, 'EUR');
        $this->assertBasketTotalMatchesOrder($items, $order);
        self::assertSame(3, array_sum(array_map(static fn (Item $item): int => (int) $item->getQuantity(), $goods)));

        foreach ($goods as $good) {
            self::assertSame('TB01378', $good->getProductReference());
            self::assertSame('p-TB01378', $good->getEuropeanArticleNumbering());
            self::assertSame('Product TB01378', $good->getName());
            self::assertSame(
                OneyStandardCategory::CarAndMotorcycle->value,
                $good->getProductCategory(),
                'Every product line must carry its Oney category.',
            );
            self::assertEquals(20.0, $good->getTaxRate());
        }

        $fees = $this->fees($items);
        self::assertCount(1, $fees);
        self::assertNotSame('', (string) $fees[0]->getProductReference());
        self::assertNotSame('', (string) $fees[0]->getName());
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function oneyPaymentProductProvider(): iterable
    {
        yield 'hosted fields, 3x' => [PluginPaymentProduct::ONEY_3X->value, []];
        yield 'hosted fields, 4x' => [PluginPaymentProduct::ONEY_4X->value, []];
        yield 'hosted fields, credit long' => [PluginPaymentProduct::ONEY_CREDIT_LONG->value, ['promotion_code' => 'PROMO12']];
        yield 'hosted page' => [self::HOSTED_PAGE, ['promotion_code' => 'PROMO12']];
    }

    private function createOneyProcessor(): OneyPaymentMethodProcessorPayment
    {
        $oneyCategory = $this->createMock(OneyCategoryInterface::class);
        $oneyCategory->method('getOneyCategory')->willReturn(OneyStandardCategory::CarAndMotorcycle);
        $categoryProvider = $this->createMock(OneyCategoryProviderInterface::class);
        $categoryProvider->method('getByProduct')->willReturn($oneyCategory);

        $oneyShippingMethod = $this->createMock(OneyShippingMethodInterface::class);
        $oneyShippingMethod->method('getOneyShippingMethod')->willReturn(OneyStandardShippingMethod::CarrierStandard);
        $oneyShippingMethod->method('getOneyPreparationTime')->willReturn(1);
        $oneyShippingMethod->method('getOneyDeliveryTime')->willReturn(2);
        $shippingMethodProvider = $this->createMock(OneyShippingMethodProviderInterface::class);
        $shippingMethodProvider->method('getByOrder')->willReturn($oneyShippingMethod);

        return new OneyPaymentMethodProcessorPayment($categoryProvider, $shippingMethodProvider, new JsonEncoder());
    }

    /**
     * @param array<string, mixed> $gatewayConfig
     */
    private function createOneyContext(
        OrderInterface $order,
        string $paymentProduct,
        array $gatewayConfig,
    ): PaymentOrderRequestContext {
        return new PaymentOrderRequestContext(
            $order,
            $this->createMock(PaymentInterface::class),
            $this->createMock(PaymentRequestInterface::class),
            $this->createMock(AccountInterface::class),
            $paymentProduct,
            [],
            $gatewayConfig,
            PaymentRequestInterface::ACTION_CAPTURE,
        );
    }
}
