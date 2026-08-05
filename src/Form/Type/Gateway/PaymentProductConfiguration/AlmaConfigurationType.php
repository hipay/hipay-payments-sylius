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

use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerRegistryInterface;
use HiPay\SyliusHiPayPlugin\Provider\AlmaLimitsProviderInterface;
use RuntimeException;
use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class AlmaConfigurationType extends GeneralConfigurationType
{
    /**
     * @param RepositoryInterface<CountryInterface> $countryRepository
     * @param RepositoryInterface<CurrencyInterface> $currencyRepository
     */
    public function __construct(
        RepositoryInterface $countryRepository,
        RepositoryInterface $currencyRepository,
        PaymentProductHandlerRegistryInterface $paymentProductHandlerRegistry,
        private readonly AlmaLimitsProviderInterface $almaLimitsProvider,
    ) {
        parent::__construct($countryRepository, $currencyRepository, $paymentProductHandlerRegistry);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $paymentProduct = $options['payment_product'];
        if (!is_string($paymentProduct)) {
            throw new RuntimeException('Option "payment_product" must be a string');
        }

        // Override min/max to be readonly — values are controlled by HiPay API
        $builder
            ->add('minimum_amount', MoneyType::class, [
                'label' => 'sylius_hipay_plugin.form.configuration.minimum_amount',
                'help' => 'sylius_hipay_plugin.form.configuration.minimum_amount_help_alma',
                'required' => false,
                'disabled' => true,
                'currency' => false,
                'divisor' => 100,
                'attr' => ['class' => 'hipay-field-readonly'],
            ])
            ->add('maximum_amount', MoneyType::class, [
                'label' => 'sylius_hipay_plugin.form.configuration.maximum_amount',
                'help' => 'sylius_hipay_plugin.form.configuration.maximum_amount_help_alma',
                'required' => false,
                'disabled' => true,
                'currency' => false,
                'divisor' => 100,
                'attr' => ['class' => 'hipay-field-readonly'],
            ])
        ;

        $accountCode = $options['account_code'] ?? null;
        if (!is_string($accountCode) || '' === $accountCode) {
            // No account selected yet: nothing to fetch, the fields stay empty.
            return;
        }

        $limits = $this->almaLimitsProvider->getLimits($accountCode, $paymentProduct);
        if (null === $limits) {
            return;
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($limits): void {
            /** @var array<string, mixed> $data */
            $data = $event->getData();
            if (!is_array($data)) {
                $data = [];
            }

            $data['minimum_amount'] = (int) round($limits['min'] * 100);
            $data['maximum_amount'] = (int) round($limits['max'] * 100);

            $event->setData($data);
        });
    }
}
