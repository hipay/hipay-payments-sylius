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

use Doctrine\ORM\EntityManagerInterface;
use HiPay\Fullservice\Enum\Cart\TypeItems;
use HiPay\Fullservice\Gateway\Model\Cart\Cart;
use HiPay\Fullservice\Gateway\Model\Cart\Item;
use HiPay\Fullservice\Gateway\Request\CommonRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\SyliusHiPayPlugin\Request\RequestContextInterface;
use HiPay\SyliusHiPayPlugin\Util\CurrencyAmount;
use Sylius\Component\Addressing\Model\Zone;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Addressing\Model\ZoneMember;
use Sylius\Component\Core\Model\Channel as CoreChannel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\Promotion;
use Sylius\Component\Core\Model\ShippingMethod;
use Sylius\Component\Core\Model\TaxRate;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Promotion\Model\PromotionAction;
use Sylius\Component\Taxation\Model\TaxCategory;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Shared harness for the HiPay basket tests (HIPASYLU005-8). Orders go through the
 * real Sylius order processor (pricing, promotions, shipping, taxes) so the basket is
 * built from the exact amounts and adjustments a merchant shop would produce.
 */
abstract class BasketTestCase extends KernelTestCase
{
    protected const LOCALE_CODE = 'en_US';

    private const BASKET_PROCESSOR_ID = 'sylius_hipay_plugin.payment_order_request.processor.basket';

    protected EntityManagerInterface $entityManager;

    protected ZoneInterface $zone;

    protected ChannelInterface $channel;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->entityManager->beginTransaction();

        $this->zone = $this->createZone();
        $this->channel = $this->createChannel('EUR');
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();
        parent::tearDown();
    }

    /**
     * Creates a tax category with a single rate applying to the test zone.
     */
    protected function createTaxCategory(string $code, float $amount, bool $includedInPrice): TaxCategoryInterface
    {
        $taxCategory = new TaxCategory();
        $taxCategory->setCode($code);
        $taxCategory->setName($code);
        $this->entityManager->persist($taxCategory);

        $taxRate = new TaxRate();
        $taxRate->setCode($code . '_rate');
        $taxRate->setName($code);
        $taxRate->setAmount($amount);
        $taxRate->setIncludedInPrice($includedInPrice);
        $taxRate->setCalculator('default');
        $taxRate->setZone($this->zone);
        $taxRate->setCategory($taxCategory);
        $this->entityManager->persist($taxRate);

        return $taxCategory;
    }

    /**
     * Channel whose default tax zone is the test zone, so taxes apply without any address.
     */
    protected function createChannel(string $currencyCode): ChannelInterface
    {
        $locale = $this->getOrCreateLocale(self::LOCALE_CODE);
        $currency = $this->getOrCreateCurrency($currencyCode);

        $channel = new CoreChannel();
        $channel->setCode('HIPAY_BASKET_' . $currencyCode);
        $channel->setName('Basket store ' . $currencyCode);
        $channel->setTaxCalculationStrategy('order_items_based');
        $channel->setDefaultTaxZone($this->zone);
        $channel->addLocale($locale);
        $channel->setDefaultLocale($locale);
        $channel->addCurrency($currency);
        $channel->setBaseCurrency($currency);
        $this->entityManager->persist($channel);

        return $channel;
    }

    /**
     * @param int $price Catalog price in the smallest currency unit (tax included or not, depending on the tax rate)
     */
    protected function createVariant(
        string $code,
        int $price,
        ?TaxCategoryInterface $taxCategory,
        bool $shippingRequired = false,
    ): ProductVariantInterface {
        $product = new Product();
        $product->setCode('p-' . $code);
        $product->setCurrentLocale(self::LOCALE_CODE);
        $product->setFallbackLocale(self::LOCALE_CODE);
        $product->setName('Product ' . $code);
        $product->setSlug(strtolower($code));
        $product->addChannel($this->channel);

        $channelPricing = new ChannelPricing();
        $channelPricing->setChannelCode($this->channel->getCode());
        $channelPricing->setPrice($price);

        $variant = new ProductVariant();
        $variant->setCode($code);
        $variant->setCurrentLocale(self::LOCALE_CODE);
        $variant->setFallbackLocale(self::LOCALE_CODE);
        $variant->setTaxCategory($taxCategory);
        $variant->setShippingRequired($shippingRequired);
        $variant->addChannelPricing($channelPricing);
        $product->addVariant($variant);

        $this->entityManager->persist($product);

        return $variant;
    }

    /**
     * Flat rate shipping method, picked as the default method for orders requiring shipping.
     */
    protected function createShippingMethod(int $amount, ?TaxCategoryInterface $taxCategory): void
    {
        $shippingMethod = new ShippingMethod();
        $shippingMethod->setCode('hipay_basket_shipping');
        $shippingMethod->setCurrentLocale(self::LOCALE_CODE);
        $shippingMethod->setFallbackLocale(self::LOCALE_CODE);
        $shippingMethod->setName('Colissimo');
        $shippingMethod->setZone($this->zone);
        $shippingMethod->setCalculator('flat_rate');
        $shippingMethod->setConfiguration([$this->channel->getCode() => ['amount' => $amount]]);
        $shippingMethod->setTaxCategory($taxCategory);
        $shippingMethod->addChannel($this->channel);
        $this->entityManager->persist($shippingMethod);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    protected function createPromotion(string $code, string $actionType, array $configuration): void
    {
        $action = new PromotionAction();
        $action->setType($actionType);
        $action->setConfiguration($configuration);

        $promotion = new Promotion();
        $promotion->setCode($code);
        $promotion->setName($code);
        $promotion->addChannel($this->channel);
        $promotion->addAction($action);
        $this->entityManager->persist($promotion);
    }

    /**
     * Builds a cart and runs it through the Sylius order processor.
     *
     * @param list<array{0: ProductVariantInterface, 1: int}> $lines Variant and quantity of each order item
     */
    protected function createOrder(array $lines): OrderInterface
    {
        $container = self::getContainer();
        $this->entityManager->flush();

        /** @var OrderInterface $order */
        $order = $container->get('sylius.factory.order')->createNew();
        $order->setChannel($this->channel);
        $order->setLocaleCode(self::LOCALE_CODE);
        $order->setCurrencyCode((string) $this->channel->getBaseCurrency()?->getCode());

        foreach ($lines as [$variant, $quantity]) {
            /** @var OrderItemInterface $item */
            $item = $container->get('sylius.factory.order_item')->createNew();
            $item->setVariant($variant);
            $item->setProductName($variant->getProduct()?->getName());
            $item->setVariantName($variant->getName());
            $container->get(OrderItemQuantityModifierInterface::class)->modify($item, $quantity);
            $order->addItem($item);
        }

        $container->get('sylius.order_processing.order_processor')->process($order);

        return $order;
    }

    /**
     * @return list<Item>
     */
    protected function buildBasket(OrderInterface $order): array
    {
        $request = new OrderRequest();
        $this->processBasket($request, $order);

        self::assertInstanceOf(Cart::class, $request->basket);

        return array_values($request->basket->getAllItems());
    }

    protected function processBasket(CommonRequest $request, OrderInterface $order): void
    {
        $context = new class($order) implements RequestContextInterface {
            public function __construct(private readonly OrderInterface $order)
            {
            }

            public function getOrder(): OrderInterface
            {
                return $this->order;
            }
        };

        self::getContainer()->get(self::BASKET_PROCESSOR_ID)->process($request, $context);
    }

    /**
     * @param list<Item> $items
     *
     * @return list<Item>
     */
    protected function goods(array $items): array
    {
        return $this->itemsOfType($items, TypeItems::GOOD);
    }

    /**
     * @param list<Item> $items
     *
     * @return list<Item>
     */
    protected function fees(array $items): array
    {
        return $this->itemsOfType($items, TypeItems::FEE);
    }

    /**
     * @param list<Item> $items
     *
     * @return list<Item>
     */
    protected function discounts(array $items): array
    {
        return $this->itemsOfType($items, TypeItems::DISCOUNT);
    }

    /**
     * @return array{quantity: mixed, unit_price: mixed, tax_rate: mixed, discount: mixed, total_amount: mixed}
     */
    protected function summarize(Item $item): array
    {
        return [
            'quantity' => $item->getQuantity(),
            'unit_price' => $item->getUnitPrice(),
            'tax_rate' => $item->getTaxRate(),
            'discount' => $item->getDiscount(),
            'total_amount' => $item->getTotalAmount(),
        ];
    }

    /**
     * Asserts the amounts sent for a basket line (major currency unit).
     */
    protected function assertLine(
        Item $item,
        int $quantity,
        float $unitPrice,
        float $taxRate,
        float $discount,
        float $totalAmount,
    ): void {
        self::assertEquals(
            [
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'discount' => $discount,
                'total_amount' => $totalAmount,
            ],
            $this->summarize($item),
            sprintf('Unexpected amounts on basket line "%s".', $item->getProductReference()),
        );
    }

    /**
     * Asserts the HiPay basket contract on every line: discount and total_amount at currency
     * precision, discount <= 0, a product reference used once, and quantity x unit_price + discount
     * rounded at currency precision equal to total_amount (HiPay rejects a gap of one unit).
     * unit_price may carry one more decimal than the currency when the total does not divide evenly.
     *
     * @param list<Item> $items
     */
    protected function assertBasketLinesAreConsistent(array $items, string $currencyCode): void
    {
        self::assertNotEmpty($items);

        $references = array_map(static fn (Item $item): string => (string) $item->getProductReference(), $items);
        self::assertSame($references, array_values(array_unique($references)), 'HiPay rejects a product reference used on several lines.');

        foreach ($items as $item) {
            $reference = (string) $item->getProductReference();
            $discount = $this->toSmallestUnit((float) $item->getDiscount(), $currencyCode, $reference, 'discount');
            $total = $this->toSmallestUnit((float) $item->getTotalAmount(), $currencyCode, $reference, 'total_amount');
            $unitPriceTenths = (float) $item->getUnitPrice() * (10 ** (CurrencyAmount::fractionDigits($currencyCode) + 1));

            self::assertEqualsWithDelta(
                round($unitPriceTenths),
                $unitPriceTenths,
                0.000001,
                sprintf('unit_price of basket line "%s" exceeds one extra decimal: %s.', $reference, $item->getUnitPrice()),
            );
            self::assertLessThanOrEqual(0, $discount, sprintf('Basket line "%s" carries a positive discount.', $reference));
            // round(quantity x unit_price + discount, currency precision) must equal total_amount.
            self::assertSame(
                $total,
                (int) round(((int) $item->getQuantity() * (int) round($unitPriceTenths) + 10 * $discount) / 10),
                sprintf('Basket line "%s" breaks quantity x unit_price + discount = total_amount.', $reference),
            );
        }
    }

    /**
     * @param list<Item> $items
     */
    protected function assertBasketTotalMatchesOrder(array $items, OrderInterface $order): void
    {
        $currencyCode = (string) $order->getCurrencyCode();
        $sum = 0;
        foreach ($items as $item) {
            $sum += $this->toSmallestUnit(
                (float) $item->getTotalAmount(),
                $currencyCode,
                (string) $item->getProductReference(),
                'total_amount',
            );
        }

        self::assertSame($order->getTotal(), $sum, 'The sum of the basket total_amount must equal the order total.');
    }

    /**
     * Converts a basket amount back to the smallest currency unit, failing when the
     * amount carries more decimals than the currency allows (e.g. 0.9996 EUR).
     */
    protected function toSmallestUnit(float $amount, string $currencyCode, string $reference, string $field): int
    {
        $scaled = $amount * (10 ** CurrencyAmount::fractionDigits($currencyCode));
        $rounded = (int) round($scaled);

        self::assertEqualsWithDelta(
            $rounded,
            $scaled,
            0.000001,
            sprintf('Field %s of basket line "%s" exceeds the %s precision: %s.', $field, $reference, $currencyCode, $amount),
        );

        return $rounded;
    }

    /**
     * @param list<Item> $items
     *
     * @return list<Item>
     */
    private function itemsOfType(array $items, string $type): array
    {
        return array_values(array_filter($items, static fn (Item $item): bool => $type === $item->getType()));
    }

    private function createZone(): ZoneInterface
    {
        $member = new ZoneMember();
        $member->setCode('FR');

        $zone = new Zone();
        $zone->setCode('HIPAY_BASKET_ZONE');
        $zone->setName('HiPay basket zone');
        $zone->setType(ZoneInterface::TYPE_COUNTRY);
        $zone->addMember($member);
        $this->entityManager->persist($zone);

        return $zone;
    }

    private function getOrCreateLocale(string $code): LocaleInterface
    {
        $locale = $this->entityManager->getRepository(Locale::class)->findOneBy(['code' => $code]);
        if (null !== $locale) {
            return $locale;
        }

        $locale = new Locale();
        $locale->setCode($code);
        $this->entityManager->persist($locale);
        $this->entityManager->flush();

        return $locale;
    }

    private function getOrCreateCurrency(string $code): CurrencyInterface
    {
        $currency = $this->entityManager->getRepository(Currency::class)->findOneBy(['code' => $code]);
        if (null !== $currency) {
            return $currency;
        }

        $currency = new Currency();
        $currency->setCode($code);
        $this->entityManager->persist($currency);
        $this->entityManager->flush();

        return $currency;
    }
}
