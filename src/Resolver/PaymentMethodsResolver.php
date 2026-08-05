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

namespace HiPay\SyliusHiPayPlugin\Resolver;

use HiPay\SyliusHiPayPlugin\Provider\GatewayProvider;
use Sylius\Component\Core\Model\PaymentInterface as CorePaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;

class PaymentMethodsResolver implements PaymentMethodsResolverInterface
{
    public function __construct(
        private readonly PaymentMethodsResolverInterface $decorated,
    ) {
    }

    public function getSupportedMethods(PaymentInterface $subject): array
    {
        $methods = $this->decorated->getSupportedMethods($subject);

        if (!$subject instanceof CorePaymentInterface) {
            return $methods;
        }

        $order = $subject->getOrder();
        if (null === $order) {
            return $methods;
        }

        $currencyCode = $order->getCurrencyCode();
        $countryCode = $order->getBillingAddress()?->getCountryCode();

        $orderTotal = $order->getTotal();

        return array_values(array_filter(
            $methods,
            function ($method) use ($currencyCode, $countryCode, $orderTotal): bool {
                if (!$method instanceof PaymentMethodInterface) {
                    return true;
                }

                return $this->isMethodEligibleForOrder($method, $currencyCode, $countryCode, $orderTotal);
            },
        ));
    }

    public function supports(PaymentInterface $subject): bool
    {
        return $this->decorated->supports($subject);
    }

    protected function isMethodEligibleForOrder(
        PaymentMethodInterface $method,
        ?string $currencyCode,
        ?string $countryCode,
        int $orderTotal,
    ): bool {
        $gatewayConfig = $method->getGatewayConfig();
        if (null === $gatewayConfig) {
            return true;
        }

        $factoryName = $gatewayConfig->getFactoryName();
        $config = $gatewayConfig->getConfig();

        return match ($factoryName) {
            GatewayProvider::FACTORY_HOSTED_FIELDS => $this->isHostedFieldsEligible($config, $currencyCode, $countryCode, $orderTotal),
            GatewayProvider::FACTORY_HOSTED_PAGE => $this->isHostedPageEligible($config, $currencyCode, $countryCode, $orderTotal),
            default => true,
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function isHostedFieldsEligible(
        array $config,
        ?string $currencyCode,
        ?string $countryCode,
        int $orderTotal,
    ): bool {
        $configuration = $config['configuration'] ?? null;

        return !is_array($configuration) ||
            $this->isConfigurationEligibleForOrder($configuration, $currencyCode, $countryCode, $orderTotal);
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function isHostedPageEligible(
        array $config,
        ?string $currencyCode,
        ?string $countryCode,
        int $orderTotal,
    ): bool {
        $products = $config['payment_products'] ?? null;
        if (!is_array($products) || [] === $products) {
            return true;
        }

        $productConfigurations = $config['product_configurations'] ?? [];
        if (!is_array($productConfigurations)) {
            $productConfigurations = [];
        }

        foreach ($products as $productCode) {
            if (!is_string($productCode)) {
                continue;
            }

            $configuration = $productConfigurations[$productCode] ?? [];
            if (!is_array($configuration)) {
                $configuration = [];
            }

            if ($this->isConfigurationEligibleForOrder($configuration, $currencyCode, $countryCode, $orderTotal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    protected function isConfigurationEligibleForOrder(
        array $configuration,
        ?string $currencyCode,
        ?string $countryCode,
        int $orderTotal,
    ): bool {
        $allowedCountries = $configuration['allowed_countries'] ?? null;
        if (is_array($allowedCountries) && [] !== $allowedCountries) {
            if (null === $countryCode || !in_array($countryCode, $allowedCountries, true)) {
                return false;
            }
        }

        $allowedCurrencies = $configuration['allowed_currencies'] ?? null;
        if (is_array($allowedCurrencies) && [] !== $allowedCurrencies) {
            if (null === $currencyCode || !in_array($currencyCode, $allowedCurrencies, true)) {
                return false;
            }
        }

        return $this->isOrderTotalWithinAmountLimits($configuration, $orderTotal);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    protected function isOrderTotalWithinAmountLimits(array $configuration, int $orderTotal): bool
    {
        // @phpstan-ignore-next-line
        $minimumAmount = (int) ($configuration['minimum_amount'] ?? 0);
        if ($minimumAmount > 0 && $orderTotal < $minimumAmount) {
            return false;
        }

        // @phpstan-ignore-next-line
        $maximumAmount = (int) ($configuration['maximum_amount'] ?? 0);

        return !($maximumAmount > 0 && $orderTotal > $maximumAmount);
    }
}
