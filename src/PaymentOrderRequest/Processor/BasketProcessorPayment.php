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
use HiPay\Fullservice\Gateway\Request\CommonRequest;
use HiPay\SyliusHiPayPlugin\Payment\Capture\Request\CaptureRequestProcessorInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestProcessorInterface;
use HiPay\SyliusHiPayPlugin\Provider\OrderTaxRateProviderInterface;
use HiPay\SyliusHiPayPlugin\Request\RequestContextInterface;
use HiPay\SyliusHiPayPlugin\Util\CurrencyAmount;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Order\Model\AdjustableInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;

/**
 * Builds the basket (items + shipping + order discounts) on any request that
 * extends CommonRequest (OrderRequest, capture MaintenanceRequest), so it serves
 * both the order-request and capture pipelines.
 * Oney-specific fields (product_category) are handled by {@see OneyPaymentMethodProcessorPayment}.
 */
final class BasketProcessorPayment implements PaymentOrderRequestProcessorInterface, CaptureRequestProcessorInterface
{
    private const ITEM_PROMOTION_ADJUSTMENT_TYPES = [
        AdjustmentInterface::ORDER_ITEM_PROMOTION_ADJUSTMENT,
        AdjustmentInterface::ORDER_UNIT_PROMOTION_ADJUSTMENT,
        AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT,
    ];

    private const DEFAULT_SHIPPING_NAME = 'Shipping';

    /**
     * Largest gap tolerated between quantity x unitPrice + discount and the line total when the
     * unit price carries an extra decimal, in tenths of the smallest currency unit (4 = 0.004 EUR).
     * Staying under half a unit keeps the line total unchanged once rounded at currency precision.
     */
    private const MAX_UNIT_PRICE_GAP = 4;

    public function __construct(
        private readonly OrderTaxRateProviderInterface $orderTaxRateProvider,
    ) {
    }

    public function process(CommonRequest $request, RequestContextInterface $context): void
    {
        $order = $context->getOrder();
        $currencyCode = (string) $order->getCurrencyCode();
        $cart = new Cart();

        /** @var OrderItemInterface $item */
        foreach ($order->getItems() as $item) {
            $variant = $item->getVariant();
            if (!$variant instanceof ProductVariantInterface) {
                continue;
            }

            if ($item->getQuantity() < 1) {
                continue;
            }

            $ean = $item->getProduct()?->getCode() ?? '';

            $line = $this->buildItemLine($order, $item, $variant);
            if (null === $line) {
                continue;
            }

            $hipayItem = new Item();
            $hipayItem
                ->setProductReference($variant->getCode() ?? '')
                ->setName($item->getProductName() ?? $variant->getCode() ?? '')
                ->setType(TypeItems::GOOD)
                ->setQuantity($line['quantity'])
                ->setUnitPrice($line['unitPrice'])
                ->setTaxRate($line['taxRate'])
                ->setDiscount(CurrencyAmount::fromSmallestUnit($line['discount'], $currencyCode))
                ->setTotalAmount(CurrencyAmount::fromSmallestUnit($line['total'], $currencyCode));

            if ('' !== $ean) {
                $hipayItem->setEuropeanArticleNumbering($ean);
            }

            $cart->addItem($hipayItem);
        }

        $this->addShippingFees($order, $cart);
        $this->addOrderDiscounts($order, $cart);

        $request->basket = $cart;
    }

    /**
     * Sends one fee line per shipment, with amounts including tax and the real tax rate.
     * A shipping promotion is sent as a negative discount, so the fee keeps its price before
     * promotion instead of an aggregated amount. Free shipping methods yield no line.
     */
    private function addShippingFees(OrderInterface $order, Cart $cart): void
    {
        $currencyCode = (string) $order->getCurrencyCode();
        $orderSeed = (string) ($order->getId() ?? '');
        $feesTotal = 0;

        /** @var ShipmentInterface $shipment */
        foreach ($order->getShipments() as $shipment) {
            $method = $shipment->getMethod();
            $taxable = $method instanceof TaxableInterface ? $method : null;
            $taxRates = $this->orderTaxRateProvider->getTaxRates($order, $shipment, $taxable);
            $total = $shipment->getAdjustmentsTotal();
            $discount = 0;
            if ($shipment->getAdjustmentsTotal(AdjustmentInterface::ORDER_SHIPPING_PROMOTION_ADJUSTMENT) < 0) {
                $shippingCharge = $shipment->getAdjustmentsTotal(AdjustmentInterface::SHIPPING_ADJUSTMENT);
                $discount = min(0, $total - $this->computeTaxIncludedUnitPrice($shippingCharge, $taxRates));
            }

            $unitPrice = $total - $discount;
            if ($unitPrice <= 0) {
                continue;
            }

            $cart->addItem(Item::buildItemTypeFees(
                $this->buildShortReference('SHIP', (string) ($shipment->getId() ?? $orderSeed)),
                (string) ($method?->getName() ?? self::DEFAULT_SHIPPING_NAME),
                CurrencyAmount::fromSmallestUnit($unitPrice, $currencyCode),
                $this->toPercentage($taxRates),
                CurrencyAmount::fromSmallestUnit($discount, $currencyCode),
                CurrencyAmount::fromSmallestUnit($total, $currencyCode),
            ));
            $feesTotal += $total;
        }

        // Shipping amounts not carried by a shipment (custom adjustments set on the order).
        $remainingTotal = $order->getShippingTotal() - $feesTotal;
        if ($remainingTotal <= 0) {
            return;
        }

        $remainingAmount = CurrencyAmount::fromSmallestUnit($remainingTotal, $currencyCode);
        $cart->addItem(Item::buildItemTypeFees(
            $this->buildShortReference('SHIP', $orderSeed . '-order'),
            self::DEFAULT_SHIPPING_NAME,
            $remainingAmount,
            0.0,
            0.0,
            $remainingAmount,
        ));
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

            $currencyCode = (string) $order->getCurrencyCode();
            $totalAmount = CurrencyAmount::fromSmallestUnit($amountCents, $currencyCode);

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
     * Builds the basket line of an order item, with amounts including tax. HiPay accepts a
     * product reference only once per basket, so the item is always sent on a single line.
     * Amounts are read unit by unit, as Sylius spreads taxes and promotions on the units.
     *
     * HiPay checks quantity x unitPrice + discount against the line total and rejects a gap of
     * one smallest currency unit. When the total is not divisible by the quantity (e.g. 3 units
     * worth 8.71), the unit price is sent with one more decimal than the currency (2.903), as
     * the other HiPay modules do. Past {@see MAX_UNIT_PRICE_GAP}, which only large quantities
     * reach, the unit price is rounded up at currency precision and the remainder joins the
     * discount, so the line stays exact.
     *
     * @return array{quantity: int, unitPrice: float, discount: int, total: int, taxRate: float}|null discount and total in the smallest currency unit
     */
    private function buildItemLine(OrderInterface $order, OrderItemInterface $item, ProductVariantInterface $variant): ?array
    {
        /** @var list<OrderItemUnitInterface> $units */
        $units = $item->getUnits()->getValues();
        if ([] === $units) {
            return null;
        }

        $quantity = count($units);
        $currencyCode = (string) $order->getCurrencyCode();

        // Adjustments set on the item itself are not part of the unit totals.
        $itemAdjustmentShares = $this->distribute($item->getAdjustmentsTotal(), $quantity);
        $hasItemPromotion = $this->sumPromotions($item) < 0;

        $total = 0;
        $discount = 0;
        $taxRates = [];
        foreach ($units as $index => $unit) {
            $taxRates = $this->orderTaxRateProvider->getTaxRates($order, $unit, $variant);
            $unitTotal = $unit->getTotal() + $itemAdjustmentShares[$index];
            $total += $unitTotal;
            if ($hasItemPromotion || $this->sumPromotions($unit) < 0) {
                $discount += min(0, $unitTotal - $this->computeTaxIncludedUnitPrice($item->getUnitPrice(), $taxRates));
            }
        }

        $line = ['quantity' => $quantity, 'discount' => $discount, 'total' => $total, 'taxRate' => $this->toPercentage($taxRates)];
        $grossTotal = $total - $discount;
        if ($grossTotal <= 0 || 0 === $grossTotal % $quantity) {
            return $line + ['unitPrice' => CurrencyAmount::fromSmallestUnit(intdiv($grossTotal, $quantity), $currencyCode)];
        }

        // Unit price in tenths of the smallest currency unit, rounded half up with integers only.
        $unitPriceTenths = intdiv(2 * 10 * $grossTotal + $quantity, 2 * $quantity);
        if (abs($quantity * $unitPriceTenths - 10 * $grossTotal) <= self::MAX_UNIT_PRICE_GAP) {
            return $line + ['unitPrice' => CurrencyAmount::fromTenthsOfSmallestUnit($unitPriceTenths, $currencyCode)];
        }

        $unitPrice = intdiv($grossTotal + $quantity - 1, $quantity);
        $line['discount'] = $total - $quantity * $unitPrice;

        return $line + ['unitPrice' => CurrencyAmount::fromSmallestUnit($unitPrice, $currencyCode)];
    }

    /**
     * Rebuilds the price including tax of a unit before any promotion. With a tax rate
     * excluded from prices, Sylius computes promotions on the tax-excluded price: the tax is
     * re-applied on the full catalog price, with the Sylius rounding, so the discount including
     * tax is the gap with the amount actually charged (never a rounded tax-excluded discount
     * multiplied by the rate).
     *
     * @param list<array{amount: float, includedInPrice: bool}> $taxRates
     */
    private function computeTaxIncludedUnitPrice(int $unitPrice, array $taxRates): int
    {
        $taxIncludedUnitPrice = $unitPrice;
        foreach ($taxRates as $taxRate) {
            if ($taxRate['includedInPrice']) {
                continue;
            }

            $taxIncludedUnitPrice += (int) round($unitPrice * $taxRate['amount']);
        }

        return $taxIncludedUnitPrice;
    }

    /**
     * Converts Sylius tax rates (0.2) to the percentage expected by HiPay (20), several
     * rates applying to the same element being summed.
     *
     * @param list<array{amount: float, includedInPrice: bool}> $taxRates
     */
    private function toPercentage(array $taxRates): float
    {
        return round(array_sum(array_column($taxRates, 'amount')) * 100, 4);
    }

    /**
     * Sums the promotions set directly on an item or a unit. Taxes are left out: with a tax
     * rate excluded from prices the tax adjustment is not neutral, and HiPay rejects it as a
     * positive discount. Order-level promotion adjustments are not part of the item, they are
     * sent as dedicated lines by {@see addOrderDiscounts()}.
     */
    private function sumPromotions(AdjustableInterface $adjustable): int
    {
        $promotionTotal = 0;
        foreach (self::ITEM_PROMOTION_ADJUSTMENT_TYPES as $adjustmentType) {
            $promotionTotal += $adjustable->getAdjustmentsTotal($adjustmentType);
        }

        return $promotionTotal;
    }

    /**
     * Splits an amount into whole parts, the remainder going to the first parts.
     *
     * @return list<int>
     */
    private function distribute(int $amount, int $parts): array
    {
        $share = intdiv($amount, $parts);
        $remainder = $amount - $share * $parts;
        $shares = array_fill(0, $parts, $share);

        for ($index = 0; $index < abs($remainder); ++$index) {
            $shares[$index] += $remainder <=> 0;
        }

        return $shares;
    }
}
