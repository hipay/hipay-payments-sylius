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

namespace HiPay\SyliusHiPayPlugin\Provider;

use HiPay\SyliusHiPayPlugin\Client\ClientProviderInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\CardPaymentProduct;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PluginPaymentProduct;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

final class PaymentProductProvider implements PaymentProductProviderInterface
{
    private ?array $availableProductCodes = null;

    public function __construct(
        private readonly ClientProviderInterface $clientProvider,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getAvailableProductCodesByAccountCode(string $accountCode): array
    {
        if (null !== $this->availableProductCodes) {
            return $this->availableProductCodes;
        }

        try {
            $client = $this->clientProvider->getForAccountCode($accountCode);
            $this->availableProductCodes = [];
            foreach ($client->getPaymentProducts() as $product) {
                $productCode = $product->getCode();
                // This fix is used because the service return each card network independently instead of the product "card"
                if (
                    CardPaymentProduct::tryFrom($product->getCode()) !== null &&
                    false === in_array(PluginPaymentProduct::CARD->value, $this->availableProductCodes, true)
                ) {
                    $productCode = PluginPaymentProduct::CARD->value;
                }
                $this->availableProductCodes[] = $productCode;
            }

            // Apple Pay is not returned as a standalone product by the HiPay API;
            // it relies on the same card networks, so mark it available when card is.
            if (
                in_array(PluginPaymentProduct::CARD->value, $this->availableProductCodes, true) &&
                !in_array(PluginPaymentProduct::APPLE_PAY->value, $this->availableProductCodes, true)
            ) {
                $this->availableProductCodes[] = PluginPaymentProduct::APPLE_PAY->value;
            }
        } catch (Throwable) {
            // API unreachable or invalid credentials: allow form to render with card as fallback
            $this->availableProductCodes = [PluginPaymentProduct::CARD->value];
        }

        return $this->availableProductCodes;
    }

    /**
     * @inheritdoc
     */
    public function getAllForChoiceList(string $accountCode): array
    {
        $productCodes = $this->getAvailableProductCodesByAccountCode($accountCode);

        $choices = [];
        foreach (PluginPaymentProduct::cases() as $product) {
            $productName = $this->translator->trans('sylius_hipay_plugin.payment_product.' . $product->value);
            $productInfo = !in_array($product->value, $productCodes, true) ? ' ' . $this->translator->trans('sylius_hipay_plugin.ui.contact_our_service_to_activate_this_product') : '';
            $choices[$productName . $productInfo] = $product->value;
        }

        return $choices;
    }
}
