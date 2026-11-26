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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\PaymentOrderRequest\Processor;

use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Exception\OneyMappingException;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\OneyPaymentMethodProcessorPayment;
use HiPay\SyliusHiPayPlugin\Provider\OneyCategoryProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\OneyShippingMethodProviderInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

final class OneyPaymentMethodProcessorPaymentTest extends TestCase
{
    /**
     * The Hosted Page stores one configuration per payment product: the promotion code of
     * Oney Credit Long is read from product_configurations, as saved by the admin form.
     */
    public function testHostedPageReadsThePromotionCodeOfTheCreditLongProduct(): void
    {
        $request = $this->createHostedPageRequest('card,credit-long');

        $this->createProcessor()->process($request, $this->createHostedPageContext([
            'product_configurations' => [
                'credit-long' => ['enabled' => true, 'configuration' => ['promotion_code' => 'GAL03']],
            ],
        ]));

        $this->assertSame(['merchant_promotion' => 'GAL03'], $request->payment_product_parameters);
    }

    public function testHostedPageReadsThePromotionCodeFromTheLegacyFlatProductConfiguration(): void
    {
        $request = $this->createHostedPageRequest('card,credit-long');

        $this->createProcessor()->process($request, $this->createHostedPageContext([
            'product_configurations' => [
                'credit-long' => ['enabled' => true, 'promotion_code' => 'GAL03'],
            ],
        ]));

        $this->assertSame(['merchant_promotion' => 'GAL03'], $request->payment_product_parameters);
    }

    public function testHostedPageWithCreditLongRequiresAPromotionCode(): void
    {
        $request = $this->createHostedPageRequest('card,credit-long');

        $this->expectException(OneyMappingException::class);

        $this->createProcessor()->process($request, $this->createHostedPageContext([
            'product_configurations' => [
                'credit-long' => ['enabled' => true, 'configuration' => ['promotion_code' => '']],
            ],
        ]));
    }

    public function testHostedPageWithoutCreditLongNeedsNoPromotionCode(): void
    {
        $request = $this->createHostedPageRequest('card,3xcb');

        $this->createProcessor()->process($request, $this->createHostedPageContext([
            'product_configurations' => ['card' => ['enabled' => true]],
        ]));

        $this->assertNull($request->payment_product_parameters ?? null);
    }

    private function createHostedPageRequest(string $paymentProductList): HostedPaymentPageRequest
    {
        $request = new HostedPaymentPageRequest();
        $request->payment_product_list = $paymentProductList;
        // A serialized basket is left untouched by the Oney category mapping.
        $request->basket = '[]';

        return $request;
    }

    /**
     * @param array<string, mixed> $gatewayConfig
     */
    private function createHostedPageContext(array $gatewayConfig): PaymentOrderRequestContext
    {
        return new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'hosted_page',
            payload: [],
            gatewayConfig: $gatewayConfig,
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );
    }

    private function createProcessor(): OneyPaymentMethodProcessorPayment
    {
        return new OneyPaymentMethodProcessorPayment(
            $this->createMock(OneyCategoryProviderInterface::class),
            $this->createMock(OneyShippingMethodProviderInterface::class),
            $this->createMock(EncoderInterface::class),
        );
    }
}
