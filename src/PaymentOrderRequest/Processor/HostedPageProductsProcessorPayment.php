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

use HiPay\Fullservice\Data\PaymentProduct;
use HiPay\Fullservice\Gateway\Request\Order\HostedPaymentPageRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestProcessorInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\PaypalConfigurationDefaultsInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PluginPaymentProduct;
use InvalidArgumentException;

/**
 * Sets payment_product_list and PayPal v2 button options for Hosted Page requests.
 *
 * Eligibility per product is evaluated against values stored at BO save time:
 * enabled flag, allowed currencies, minimum/maximum order amounts.
 * For Alma products, AlmaConfigurationType refreshes limits from the HiPay API
 * on every admin form save, so no API call is needed here.
 */
final class HostedPageProductsProcessorPayment implements PaymentOrderRequestProcessorInterface
{
    /**
     * Products not available on Hosted Page.
     * card and apple-pay are Hosted Fields only.
     */
    public const EXCLUDED_PRODUCTS = ['apple-pay'];

    public function process(OrderRequest $orderRequest, PaymentOrderRequestContext $context): void
    {
        if (!$orderRequest instanceof HostedPaymentPageRequest) {
            return;
        }

        $products = $this->resolvePaymentProducts($context);
        $orderRequest->payment_product_list = implode(',', array_map(
            static fn (PaymentProduct $paymentProduct): string => $paymentProduct->getProductCode(),
            $products,
        ));
        unset($orderRequest->payment_product);

        $productCodes = array_map(static fn (PaymentProduct $paymentProduct): string => $paymentProduct->getProductCode(), $products);
        if (!in_array(PluginPaymentProduct::PAYPAL->value, $productCodes, true)) {
            return;
        }

        /** @var array<string, mixed> $productConfigurations */
        $productConfigurations = $context->gatewayConfig['product_configurations'] ?? [];
        $paypalConfig = $productConfigurations[PluginPaymentProduct::PAYPAL->value] ?? [];
        if (!is_array($paypalConfig)) {
            return;
        }

        // New structure: config is nested under 'configuration'; old structure: flat
        $paypalSpecificConfig = isset($paypalConfig['configuration']) && is_array($paypalConfig['configuration'])
            ? $paypalConfig['configuration']
            : $paypalConfig;

        $orderRequest->paypal_v2_label = $paypalSpecificConfig['button_label'] ?? PaypalConfigurationDefaultsInterface::BUTTON_LABEL;
        $orderRequest->paypal_v2_shape = $paypalSpecificConfig['button_shape'] ?? PaypalConfigurationDefaultsInterface::BUTTON_SHAPE;
        $orderRequest->paypal_v2_color = $paypalSpecificConfig['button_color'] ?? PaypalConfigurationDefaultsInterface::BUTTON_COLOR;
        $orderRequest->paypal_v2_height = $paypalSpecificConfig['button_height'] ?? PaypalConfigurationDefaultsInterface::BUTTON_HEIGHT;
        $orderRequest->paypal_v2_bnpl = $paypalSpecificConfig['can_pay_later'] ?? PaypalConfigurationDefaultsInterface::CAN_PAY_LATER;
    }

    /**
     * @return PaymentProduct[]
     */
    private function resolvePaymentProducts(PaymentOrderRequestContext $context): array
    {
        $productConfigs = $context->gatewayConfig['product_configurations'] ?? null;

        if (null === $productConfigs) {
            return $this->resolveFromLegacyConfig($context->gatewayConfig);
        }

        $eligible = $this->collectEligibleProducts($context, (array) $productConfigs);

        if ([] === $eligible) {
            throw new InvalidArgumentException('Hosted Page payment products are not configured.');
        }

        return $eligible;
    }

    /**
     * Backward compat: resolve products from the old flat payment_products array.
     *
     * @param array<string, mixed> $gatewayConfig
     *
     * @return PaymentProduct[]
     */
    private function resolveFromLegacyConfig(array $gatewayConfig): array
    {
        $rawProducts = $gatewayConfig['payment_products'] ?? null;
        if (!is_array($rawProducts)) {
            throw new InvalidArgumentException('Hosted Page payment products are not configured.');
        }

        $products = array_values(array_filter(
            $rawProducts,
            static fn (mixed $product): bool => is_string($product) && '' !== $product,
        ));

        if ([] === $products) {
            throw new InvalidArgumentException('Hosted Page payment products are not configured.');
        }

        return array_map(
            static fn (string $code): PaymentProduct => new PaymentProduct(['productCode' => trim($code), 'priority' => 99]),
            $products,
        );
    }

    /**
     * Returns eligible products as PaymentProduct objects sorted by BO position (ascending).
     * Using BO position as priority overrides HiPay SDK's built-in JSON priorities.
     *
     * @param array<mixed> $productConfigs
     *
     * @return PaymentProduct[]
     */
    private function collectEligibleProducts(PaymentOrderRequestContext $context, array $productConfigs): array
    {
        $currencyCode = $context->order->getCurrencyCode() ?? '';
        $countryCode = $context->order->getBillingAddress()?->getCountryCode() ?? '';
        $amount = $context->payment->getAmount() ?? 0;

        /** @var PaymentProduct[] $eligible */
        $eligible = [];
        foreach ($productConfigs as $code => $config) {
            if (!is_string($code) || '' === $code || in_array($code, self::EXCLUDED_PRODUCTS, true)) {
                continue;
            }

            $isEnabled = is_array($config) ? ($config['enabled'] ?? true) : true;
            if (!(bool) $isEnabled) {
                continue;
            }

            /** @var array<string, mixed> $configuration */
            $configuration = is_array($config) && isset($config['configuration']) && is_array($config['configuration'])
                ? $config['configuration']
                : [];

            if (!$this->isEligible($configuration, $currencyCode, $countryCode, $amount)) {
                continue;
            }

            $priority = is_array($config) ? (int) ($config['position'] ?? 99) : 99;
            $eligible[] = new PaymentProduct(['productCode' => $code, 'priority' => $priority]);
        }

        usort($eligible, static fn (PaymentProduct $paymentProductA, PaymentProduct $paymentProductB): int => $paymentProductA->getPriority() <=> $paymentProductB->getPriority());

        return $eligible;
    }

    /**
     * @param array<string, mixed> $configuration Stored product configuration (currencies, countries, amounts)
     */
    private function isEligible(array $configuration, string $currencyCode, string $countryCode, int $amount): bool
    {
        // Currency check: empty list means all currencies are allowed
        $allowedCurrencies = $configuration['allowed_currencies'] ?? [];
        if (is_array($allowedCurrencies) && [] !== $allowedCurrencies && !in_array($currencyCode, $allowedCurrencies, true)) {
            return false;
        }

        // Country check: empty list means all countries are allowed
        $allowedCountries = $configuration['allowed_countries'] ?? [];
        if (is_array($allowedCountries) && [] !== $allowedCountries && !in_array($countryCode, $allowedCountries, true)) {
            return false;
        }

        $minRaw = $configuration['minimum_amount'] ?? null;
        $maxRaw = $configuration['maximum_amount'] ?? null;
        $minAmount = is_scalar($minRaw) ? (int) $minRaw : null;
        $maxAmount = is_scalar($maxRaw) ? (int) $maxRaw : null;

        if (null !== $minAmount && $minAmount > 0 && $amount < $minAmount) {
            return false;
        }

        if (null !== $maxAmount && $maxAmount > 0 && $amount > $maxAmount) {
            return false;
        }

        return true;
    }
}
