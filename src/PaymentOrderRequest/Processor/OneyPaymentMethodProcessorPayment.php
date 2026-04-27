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

namespace HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor;

use DateTimeImmutable;
use HiPay\Fullservice\Enum\Cart\TypeItems;
use HiPay\Fullservice\Gateway\Model\Cart\Item;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\Fullservice\Gateway\Request\PaymentMethod\XTimesCreditCardPaymentMethod;
use HiPay\SyliusHiPayPlugin\Entity\OneyShippingMethodInterface;
use HiPay\SyliusHiPayPlugin\Exception\OneyMappingException;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestProcessorInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PluginPaymentProduct;
use HiPay\SyliusHiPayPlugin\Provider\OneyCategoryProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\OneyShippingMethodProviderInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

/**
 * Oney (3xcb, 4xcb, credit-long, …): sets {@see OrderRequest::payment_product} from context and builds
 * {@see OneyHostedFieldsPaymentMethod} from Hosted Fields payload + order / admin Oney mappings.
 */
final class OneyPaymentMethodProcessorPayment implements PaymentOrderRequestProcessorInterface
{
    public function __construct(
        private readonly OneyCategoryProviderInterface $oneyCategoryProvider,
        private readonly OneyShippingMethodProviderInterface $oneyShippingMethodProvider,
        private readonly EncoderInterface $serializer,
    ) {
    }

    public function process(OrderRequest $orderRequest, PaymentOrderRequestContext $context): void
    {
        $orderRequest->payment_product = $context->paymentProduct;
        $method = new XTimesCreditCardPaymentMethod();

        $order = $context->order;

        $this->applyShippingDataAndPhone($method, $order);
        $orderRequest->paymentMethod = $method;

        $this->applyProductCategory($orderRequest, $order);

        if (PluginPaymentProduct::ONEY_CREDIT_LONG->value === $context->paymentProduct) {
            /** @var array $configuration */
            $configuration = $context->gatewayConfig['configuration'] ?? $context->gatewayConfig;
            $promotionCode = $configuration['promotion_code'] ?? null;

            if (null === $promotionCode || '' === trim((string) $promotionCode)) {
                throw new OneyMappingException('Oney Credit Long requires a promotion code (promotion_code)');
            }

            $orderRequest->payment_product_parameters = ['merchant_promotion' => $promotionCode];
        }
    }

    private function applyShippingDataAndPhone(XTimesCreditCardPaymentMethod $method, OrderInterface $order): void
    {
        $shippingAddress = $order->getShippingAddress();
        $billingAddress = $order->getBillingAddress();
        $address = $shippingAddress ?? $billingAddress;

        $phone = $this->resolvePhone($address, $order);
        $method->shipto_phone = $phone;

        $oneyShipping = $this->oneyShippingMethodProvider->getByOrder($order);

        $oneyDelivery = $oneyShipping?->getOneyShippingMethod();
        if (null === $oneyDelivery) {
            throw new OneyMappingException('Oney shipping mapping has no delivery method type');
        }

        $method->delivery_method = $this->serializer->encode($oneyDelivery->toArray(), 'json');
        $method->delivery_date = $this->resolveDeliveryDate($oneyShipping)->format('Y-m-d');
    }

    private function applyProductCategory(OrderRequest $orderRequest, OrderInterface $order): void
    {
        $cart = $orderRequest->basket;
        if (is_string($cart)) {
            return;
        }

        /** @var Item $hipayItem */
        foreach ($cart->getAllItems() as $hipayItem) {
            if (TypeItems::GOOD !== $hipayItem->getType()) {
                continue;
            }
            $product = $this->getProductByVariantCode($order, $hipayItem->getProductReference());
            $productCategory = $this->resolveProductCategoryForItem($product);
            if (null === $productCategory) {
                throw new OneyMappingException(sprintf('Oney category mapping has no category type for the product %s', $product?->getCode()));
            }
            // @phpstan-ignore-next-line
            $hipayItem->setProductCategory($productCategory);
        }
    }

    private function getProductByVariantCode(OrderInterface $order, string $variantCode): ?ProductInterface
    {
        $product = null;
        foreach ($order->getItems() as $item) {
            if ($item->getVariant()?->getCode() === $variantCode) {
                $product = $item->getProduct();

                break;
            }
        }

        return $product;
    }

    private function resolveProductCategoryForItem(?ProductInterface $product): ?int
    {
        if (null === $product) {
            return null;
        }

        $oneyCategory = $this->oneyCategoryProvider->getByProduct($product);

        return $oneyCategory?->getOneyCategory()?->value;
    }

    private function resolvePhone(?AddressInterface $address, OrderInterface $order): string
    {
        $phone = $address?->getPhoneNumber();
        if (null === $phone) {
            $customer = $order->getCustomer();
            $phone = $customer?->getPhoneNumber();
        }

        return null === $phone ? '' : str_replace(' ', '', $phone);
    }

    private function resolveDeliveryDate(OneyShippingMethodInterface $oneyShipping): DateTimeImmutable
    {
        $now = new DateTimeImmutable();
        $daysToAdd = $oneyShipping->getOneyPreparationTime() + $oneyShipping->getOneyDeliveryTime();

        return $now->modify(sprintf('+%d days', $daysToAdd));
    }
}
