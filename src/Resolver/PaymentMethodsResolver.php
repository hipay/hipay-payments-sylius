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

use Sylius\Component\Core\Model\PaymentInterface as CorePaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Resolver\PaymentMethodsResolverInterface;

class PaymentMethodsResolver implements PaymentMethodsResolverInterface
{
    private const HIPAY_HOSTED_FIELDS_FACTORY = 'hipay_hosted_fields';

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
        if (null === $gatewayConfig || self::HIPAY_HOSTED_FIELDS_FACTORY !== $gatewayConfig->getFactoryName()) {
            return true;
        }

        $config = $gatewayConfig->getConfig();
        $configuration = $config['configuration'] ?? null;
        if (!is_array($configuration)) {
            return true;
        }

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

        if (!$this->isOrderTotalWithinAmountLimits($configuration, $orderTotal)) {
            return false;
        }

        return true;
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
