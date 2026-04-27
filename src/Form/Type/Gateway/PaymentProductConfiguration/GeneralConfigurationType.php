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

namespace HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration;

use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerRegistryInterface;
use RuntimeException;
use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GeneralConfigurationType extends AbstractType
{
    /**
     * @param RepositoryInterface<CountryInterface> $countryRepository
     * @param RepositoryInterface<CurrencyInterface> $currencyRepository
     */
    public function __construct(
        private readonly RepositoryInterface $countryRepository,
        private readonly RepositoryInterface $currencyRepository,
        private readonly PaymentProductHandlerRegistryInterface $paymentProductHandlerRegistry,
    ) {
    }

    /**
     * @param array<string|'payment_product',string> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $paymentProductHandler = $this->paymentProductHandlerRegistry->getForPaymentProduct($options['payment_product']);
        if (!$paymentProductHandler instanceof PaymentProductHandlerInterface) {
            throw new RuntimeException('No payment product handler registered');
        }

        $builder
            ->add('allowed_countries', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.configuration.allowed_countries',
                'help' => 'sylius_hipay_plugin.form.configuration.allowed_countries_help',
                'choices' => $this->getCountryChoices($paymentProductHandler),
                'multiple' => true,
                'required' => false,
            ])
            ->add('allowed_currencies', ChoiceType::class, [
                'label' => 'sylius_hipay_plugin.form.configuration.allowed_currencies',
                'help' => 'sylius_hipay_plugin.form.configuration.allowed_currencies_help',
                'choices' => $this->getCurrencyChoices($paymentProductHandler),
                'multiple' => true,
                'required' => false,
            ])
            ->add('minimum_amount', MoneyType::class, [
                'label' => 'sylius_hipay_plugin.form.configuration.minimum_amount',
                'help' => 'sylius_hipay_plugin.form.configuration.minimum_amount_help',
                'required' => false,
                'currency' => false,
                'divisor' => 100,
            ])
            ->add('maximum_amount', MoneyType::class, [
                'label' => 'sylius_hipay_plugin.form.configuration.maximum_amount',
                'help' => 'sylius_hipay_plugin.form.configuration.maximum_amount_help',
                'required' => false,
                'currency' => false,
                'divisor' => 100,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver
            ->setDefined('payment_product')
            ->setAllowedTypes('payment_product', 'string')
            ->isRequired('payment_product')
        ;
    }

    /**
     * Country choices for allowed_countries: all enabled countries, or only those allowed
     * by the payment product handler when it returns a non-empty list.
     *
     * @return array<string, string>
     */
    protected function getCountryChoices(PaymentProductHandlerInterface $paymentProductHandler): array
    {
        $choices = $this->buildAllCountryChoices();

        return $this->filterChoicesByAllowedCodes(
            $choices,
            $paymentProductHandler->getAvailableCountries(),
        );
    }

    /**
     * Currency choices for allowed_currencies: all currencies, or only those allowed
     * by the payment product handler when it returns a non-empty list.
     *
     * @return array<string, string>
     */
    protected function getCurrencyChoices(PaymentProductHandlerInterface $paymentProductHandler): array
    {
        $choices = $this->buildAllCurrencyChoices();

        return $this->filterChoicesByAllowedCodes(
            $choices,
            $paymentProductHandler->getAvailableCurrencies(),
        );
    }

    /**
     * @param array<string, string> $choices
     * @param string[] $allowedCodes
     *
     * @return array<string, string>
     */
    protected function filterChoicesByAllowedCodes(array $choices, array $allowedCodes): array
    {
        if ($allowedCodes === []) {
            return $choices;
        }

        $allowed = array_flip($allowedCodes);

        return array_filter(
            $choices,
            static fn (string $code): bool => isset($allowed[$code]),
        );
    }

    /** @return array<string, string> */
    protected function buildAllCountryChoices(): array
    {
        $choices = [];
        /** @var CountryInterface $country */
        foreach ($this->countryRepository->findBy(['enabled' => true]) as $country) {
            $name = $country->getName();
            $code = $country->getCode();
            if (null !== $name && null !== $code) {
                $choices[$name] = $code;
            }
        }
        ksort($choices);

        return $choices;
    }

    /** @return array<string, string> */
    protected function buildAllCurrencyChoices(): array
    {
        $choices = [];
        /** @var CurrencyInterface $currency */
        foreach ($this->currencyRepository->findAll() as $currency) {
            $name = $currency->getName();
            $code = $currency->getCode();
            if (null !== $name && null !== $code) {
                $choices[$name] = $code;
            }
        }
        ksort($choices);

        return $choices;
    }
}
