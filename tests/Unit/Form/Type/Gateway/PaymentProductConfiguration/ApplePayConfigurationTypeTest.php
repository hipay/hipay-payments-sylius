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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Form\Type\Gateway\PaymentProductConfiguration;

use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\ApplePayConfigurationType;
use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\GeneralConfigurationType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerRegistryInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

/**
 * Covers the two risk paths in ApplePayConfigurationType:
 *  - SUBMIT listener: clears merchant_identifier when only_active_card is false
 *  - Callback constraint: violation when only_active_card=true and merchant_identifier is blank
 */
final class ApplePayConfigurationTypeTest extends TestCase
{
    private FormFactoryInterface $factory;

    protected function setUp(): void
    {
        $validator = Validation::createValidator();

        /** @var RepositoryInterface<CountryInterface> $countryRepo */
        $countryRepo = $this->createMock(RepositoryInterface::class);
        $countryRepo->method('findBy')->willReturn([]);

        /** @var RepositoryInterface<CurrencyInterface> $currencyRepo */
        $currencyRepo = $this->createMock(RepositoryInterface::class);
        $currencyRepo->method('findAll')->willReturn([]);

        $handler = $this->createMock(PaymentProductHandlerInterface::class);
        $handler->method('getAvailableCountries')->willReturn([]);
        $handler->method('getAvailableCurrencies')->willReturn([]);

        $registry = $this->createMock(PaymentProductHandlerRegistryInterface::class);
        $registry->method('getForPaymentProduct')->willReturn($handler);

        $generalType = new GeneralConfigurationType($countryRepo, $currencyRepo, $registry);

        $this->factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension($validator))
            ->addType($generalType)
            ->getFormFactory();
    }

    private function buildForm(array $options = []): FormInterface
    {
        return $this->factory
            ->createBuilder(ApplePayConfigurationType::class, [], array_merge([
                'payment_product' => 'apple-pay',
                'validation_groups' => ['sylius'],
            ], $options))
            ->getForm();
    }

    private function submitBase(FormInterface $form, array $overrides = []): FormInterface
    {
        $form->submit(array_merge([
            'only_active_card' => false,
            'merchant_identifier' => '',
            'display_name' => '',
            'supported_networks' => ['visa'],
            'button_type' => 'buy',
            'button_color' => 'black',
            'multi_browser' => false,
            'display_mode' => 'popup',
        ], $overrides));

        return $form;
    }

    // --- SUBMIT listener: merchant_identifier cleanup ---

    public function testSubmitClearsMerchantIdentifierWhenOnlyActiveCardIsFalse(): void
    {
        $form = $this->submitBase($this->buildForm(['constraints' => []]), [
            'only_active_card' => false,
            'merchant_identifier' => 'merchant.com.example',
        ]);

        /** @var array<string, mixed> $data */
        $data = $form->getData();
        $this->assertSame('', $data['merchant_identifier']);
    }

    public function testSubmitPreservesMerchantIdentifierWhenOnlyActiveCardIsTrue(): void
    {
        $form = $this->submitBase($this->buildForm(['constraints' => []]), [
            'only_active_card' => true,
            'merchant_identifier' => 'merchant.com.example',
        ]);

        /** @var array<string, mixed> $data */
        $data = $form->getData();
        $this->assertSame('merchant.com.example', $data['merchant_identifier']);
    }

    // --- Callback constraint: merchant_identifier required when only_active_card=true ---

    public function testValidationPassesWhenOnlyActiveCardIsTrueAndMerchantIdentifierIsProvided(): void
    {
        $form = $this->submitBase($this->buildForm(), [
            'only_active_card' => true,
            'merchant_identifier' => 'merchant.com.example',
        ]);

        $rootErrors = iterator_to_array($form->getErrors(true, true));
        $messages = array_map(static fn ($e) => $e->getMessage(), $rootErrors);

        $this->assertNotContains(
            'sylius_hipay_plugin.apple_pay.merchant_identifier.required_when_only_active_card',
            $messages,
        );
    }

    public function testValidationFailsWhenOnlyActiveCardIsTrueAndMerchantIdentifierIsBlank(): void
    {
        $form = $this->submitBase($this->buildForm(), [
            'only_active_card' => true,
            'merchant_identifier' => '',
        ]);

        $rootErrors = iterator_to_array($form->getErrors(true, true));
        $messages = array_map(static fn ($e) => $e->getMessage(), $rootErrors);

        $this->assertContains(
            'sylius_hipay_plugin.apple_pay.merchant_identifier.required_when_only_active_card',
            $messages,
        );
    }

    public function testValidationPassesWhenOnlyActiveCardIsFalseRegardlessOfMerchantIdentifier(): void
    {
        $form = $this->submitBase($this->buildForm(), [
            'only_active_card' => false,
            'merchant_identifier' => '',
        ]);

        $rootErrors = iterator_to_array($form->getErrors(true, true));
        $messages = array_map(static fn ($e) => $e->getMessage(), $rootErrors);

        $this->assertNotContains(
            'sylius_hipay_plugin.apple_pay.merchant_identifier.required_when_only_active_card',
            $messages,
        );
    }

    public function testValidationFailsWhenOnlyActiveCardIsTrueAndMerchantIdentifierIsWhitespaceOnly(): void
    {
        $form = $this->submitBase($this->buildForm(), [
            'only_active_card' => true,
            'merchant_identifier' => '   ',
        ]);

        $rootErrors = iterator_to_array($form->getErrors(true, true));
        $messages = array_map(static fn ($e) => $e->getMessage(), $rootErrors);

        $this->assertContains(
            'sylius_hipay_plugin.apple_pay.merchant_identifier.required_when_only_active_card',
            $messages,
        );
    }
}
