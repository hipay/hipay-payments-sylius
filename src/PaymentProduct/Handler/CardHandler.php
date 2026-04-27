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

namespace HiPay\SyliusHiPayPlugin\PaymentProduct\Handler;

use HiPay\SyliusHiPayPlugin\Entity\SavedCardInterface;
use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\CardConfigurationType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\CardConfigurationDefaultsInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerInterface;
use HiPay\SyliusHiPayPlugin\Repository\SavedCardRepositoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Symfony\Component\Clock\ClockInterface;

class CardHandler extends AbstractPaymentProductHandler implements PaymentProductHandlerInterface
{
    protected string $code = 'card';

    protected string $name = 'hipay.payment_product.card';

    public function __construct(
        private CustomerContextInterface $customerContext,
        private SavedCardRepositoryInterface $savedCardRepository,
        private ClockInterface $clock,
    ) {
    }

    public function supports(string $paymentProduct): bool
    {
        return in_array($paymentProduct, [
            'card', 'visa', 'mastercard', 'cb', 'maestro',
            'american-express', 'bcmc',
        ], true);
    }

    public function getFormType(): string
    {
        return CardConfigurationType::class;
    }

    public function getJsInitConfig(PaymentMethodInterface $paymentMethod, ?PaymentInterface $payment = null): array
    {
        /** @var array<string, string|bool|int>|null $cardConfig */
        $cardConfig = $paymentMethod->getGatewayConfig()?->getConfig()['configuration'] ?? null;
        if (null === $cardConfig) {
            return [];
        }

        $billingAddress = $payment?->getOrder()?->getBillingAddress();

        $config = [
            'template' => 'auto',
            'fields' => [
                'cardHolder' => [
                    'uppercase' => true,
                    'defaultFirstname' => (string) ($billingAddress?->getFirstName() ?? ''),
                    'defaultLastname' => (string) ($billingAddress?->getLastName() ?? ''),
                ],
                'cardNumber' => [
                    'hideCardTypeLogo' => false,
                    'displayAcceptedCards' => true,
                ],
                'cvc' => [
                    'helpButton' => true,
                ],
            ],
            'styles' => [
                'base' => [
                    'color' => $cardConfig['text_color'] ?? CardConfigurationDefaultsInterface::TEXT_COLOR,
                    'fontSize' => $cardConfig['font_size'] ?? CardConfigurationDefaultsInterface::FONT_SIZE,
                    'fontFamily' => $cardConfig['font_family'] ?? CardConfigurationDefaultsInterface::FONT_FAMILY,
                    'fontWeight' => $cardConfig['font_weight'] ?? CardConfigurationDefaultsInterface::FONT_WEIGHT,
                    'fontStyle' => $cardConfig['font_style'] ?? CardConfigurationDefaultsInterface::FONT_STYLE,
                    'textDecoration' => $cardConfig['text_decoration'] ?? CardConfigurationDefaultsInterface::TEXT_DECORATION,
                    'placeholderColor' => $cardConfig['placeholder_color'] ?? CardConfigurationDefaultsInterface::PLACEHOLDER_COLOR,
                    'iconColor' => $cardConfig['icon_color'] ?? CardConfigurationDefaultsInterface::ICON_COLOR,
                ],
                'valid' => [
                    'color' => $cardConfig['valid_text_color'] ?? CardConfigurationDefaultsInterface::VALID_TEXT_COLOR,
                ],
                'invalid' => [
                    'color' => $cardConfig['invalid_text_color'] ?? CardConfigurationDefaultsInterface::INVALID_TEXT_COLOR,
                ],
            ],
        ];

        $allowedBrands = $cardConfig['allowed_brands'] ?? [];
        if (!empty($allowedBrands)) {
            $config['brand'] = $allowedBrands;
        }

        $this->manageSavedCards($config, $cardConfig);

        return $config;
    }

    protected function manageSavedCards(array &$config, array $cardConfig): void
    {
        $oneClickEnabled = (bool) ($cardConfig['one_click_enabled'] ?? false);
        if (false === $oneClickEnabled) {
            return;
        }

        $customer = $this->customerContext->getCustomer();
        $cards = [];
        if (null !== $customer) {
            $savedCards = $this->savedCardRepository->findEligibleByCustomer($customer, $this->clock->now());
            /** @var SavedCardInterface $savedCard */
            foreach ($savedCards as $savedCard) {
                $card = [
                    'token' => $savedCard->getToken(),
                    'brand' => $savedCard->getBrand(),
                    'pan' => $savedCard->getMaskedPan(),
                    'card_expiry_month' => $savedCard->getExpiryMonth(),
                    'card_expiry_year' => $savedCard->getExpiryYear(),
                ];
                if (null !== $savedCard->getHolder()) {
                    $card['card_holder'] = $savedCard->getHolder();
                }
                $cards[] = $card;
            }
        }

        $config['one_click'] = [
            'enabled' => true,
            'cards_display_count' => max(1, count($cards)),
            'cards' => $cards,
            'styles' => [
                'highlightColor' => $cardConfig['oneclick_highlight_color'] ?? CardConfigurationDefaultsInterface::ONECLICK_HIGHLIGHT_COLOR,
                'saveButtonColor' => $cardConfig['save_button_color'] ?? CardConfigurationDefaultsInterface::SAVE_BUTTON_COLOR,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * HiPay card schemes (Visa, Mastercard, Amex, Maestro, CB, etc.) are offered across
     * 85+ countries; exact coverage is contract- and scheme-dependent (see Payment Means).
     * Returning an empty array means no plugin-side country restriction — merchant gateway
     * configuration (allowed_countries) and HiPay account scope still apply.
     *
     * @see https://developer.hipay.com/online-payments/payment-means/payment-means
     */
    public function getAvailableCountries(): array
    {
        // Empty = worldwide per PaymentProductHandlerInterface; do not narrow without
        // a definitive ISO list from HiPay for the merchant's contract.
        return [];
    }

    /**
     * {@inheritdoc}
     *
     * HiPay supports 150+ currencies for online card payments; popular examples from
     * HiPay communications: EUR, GBP, CHF, SEK, DKK, NOK, PLN, CZK, USD, CAD, JPY, HKD,
     * AUD, ZAR. There is no public exhaustive ISO 4217 list — availability depends on
     * the HiPay contract and acquirer. Empty = no plugin-side currency restriction.
     *
     * @see https://developer.hipay.com/online-payments/payment-means/payment-means
     */
    public function getAvailableCurrencies(): array
    {
        // Empty = all currencies accepted at gateway level per interface; narrowing here
        // would incorrectly reject valid orders (e.g. EUR-only merchant with PLN in list).
        return [];
    }
}
