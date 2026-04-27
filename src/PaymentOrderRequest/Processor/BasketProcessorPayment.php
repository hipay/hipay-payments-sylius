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

use HiPay\Fullservice\Enum\Cart\TypeItems;
use HiPay\Fullservice\Gateway\Model\Cart\Cart;
use HiPay\Fullservice\Gateway\Model\Cart\Item;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestProcessorInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

/**
 * Builds the basket (shopping cart) for every payment product.
 * Oney-specific fields (product_category) are handled by {@see OneyPaymentMethodProcessorPayment}.
 */
final class BasketProcessorPayment implements PaymentOrderRequestProcessorInterface
{
    public function process(OrderRequest $orderRequest, PaymentOrderRequestContext $context): void
    {
        $order = $context->order;
        $cart = new Cart();

        /** @var OrderItemInterface $item */
        foreach ($order->getItems() as $item) {
            $variant = $item->getVariant();
            if (!$variant instanceof ProductVariantInterface) {
                continue;
            }

            $quantity = $item->getQuantity();
            if ($quantity < 1) {
                continue;
            }

            $product = $item->getProduct();

            $hipayItem = new Item();
            $hipayItem
                ->setProductReference($variant->getCode() ?? '')
                ->setName($item->getProductName() ?? $variant->getCode() ?? '')
                ->setType(TypeItems::GOOD)
                ->setQuantity($quantity)
                ->setUnitPrice($item->getUnitPrice() / 100)
                ->setTaxRate($this->computeTaxRate($item))
                ->setDiscount($item->getAdjustmentsTotalRecursively() / 100)
                ->setTotalAmount($item->getTotal() / 100);

            $ean = $product?->getCode() ?? '';
            if ('' !== $ean) {
                $hipayItem->setEuropeanArticleNumbering($ean);
            }

            $cart->addItem($hipayItem);
        }

        $this->addShippingFee($order, $cart);
        $this->addOrderDiscounts($order, $cart);

        $orderRequest->basket = $cart;
    }

    private function addShippingFee(OrderInterface $order, Cart $cart): void
    {
        $shippingTotal = $order->getShippingTotal();
        if ($shippingTotal <= 0) {
            return;
        }

        $amount = $shippingTotal / 100;
        $name = 'Shipping';
        $firstShipment = $order->getShipments()->first();
        $referenceSeed = (string) ($order->getId() ?? '');
        if (false !== $firstShipment) {
            $referenceSeed = (string) ($firstShipment->getId() ?? $referenceSeed);
            $method = $firstShipment->getMethod();
            if (null !== $method) {
                $name = (string) $method->getName();
            }
        }

        $hipayItem = Item::buildItemTypeFees(
            $this->buildShortReference('SHIP', $referenceSeed),
            $name,
            $amount,
            0.0,
            0.0,
            $amount,
        );
        $cart->addItem($hipayItem);
    }

    /**
     * Order-level promotion adjustments (not already embedded in line item totals).
     */
    private function addOrderDiscounts(OrderInterface $order, Cart $cart): void
    {
        foreach ($order->getAdjustments(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT) as $adjustment) {
            if ($adjustment->isNeutral() || 0 === $adjustment->getAmount()) {
                continue;
            }

            $amountCents = $adjustment->getAmount();
            if ($amountCents >= 0) {
                continue;
            }

            $totalAmount = $amountCents / 100;
            $referenceSeed = (string) ($adjustment->getId() ?? spl_object_id($adjustment));
            $label = $adjustment->getLabel() ?? 'Discount';

            $hipayItem = Item::buildItemTypeDiscount(
                $this->buildShortReference('DISC', $referenceSeed),
                $label,
                0.0,
                0.0,
                $totalAmount,
                $label,
                $totalAmount,
            );
            $cart->addItem($hipayItem);
        }
    }

    private function buildShortReference(string $prefix, string $seed): string
    {
        $suffix = strtoupper(substr(hash('sha256', $seed), 0, 5));

        return sprintf('%s-%s', $prefix, $suffix);
    }

    /**
     * Computes the effective tax rate (percentage) from item totals.
     */
    private function computeTaxRate(OrderItemInterface $item): float
    {
        $totalWithoutTax = $item->getTotal() - $item->getTaxTotal();
        if ($totalWithoutTax <= 0) {
            return 0;
        }

        return round(($item->getTaxTotal() / $totalWithoutTax) * 100, 2);
    }
}
