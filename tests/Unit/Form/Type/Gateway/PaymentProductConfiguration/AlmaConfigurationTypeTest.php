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

use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\HostedPageProductConfigurationType;
use HiPay\SyliusHiPayPlugin\Form\Type\Gateway\PaymentProductConfiguration\AlmaConfigurationType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerInterface;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PaymentProductHandlerRegistryInterface;
use HiPay\SyliusHiPayPlugin\Provider\AlmaLimitsProviderInterface;
use Locale;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

final class AlmaConfigurationTypeTest extends TestCase
{
    private MockObject&AlmaLimitsProviderInterface $almaLimitsProvider;

    private FormFactoryInterface $factory;

    private string $defaultLocale;

    protected function setUp(): void
    {
        // MoneyType renders through the default locale; pin it so view assertions are stable.
        $this->defaultLocale = Locale::getDefault();
        Locale::setDefault('en');

        $this->almaLimitsProvider = $this->createMock(AlmaLimitsProviderInterface::class);

        $countryRepo = $this->createMock(RepositoryInterface::class);
        $countryRepo->method('findBy')->willReturn([]);

        $currencyRepo = $this->createMock(RepositoryInterface::class);
        $currencyRepo->method('findAll')->willReturn([]);

        $handler = $this->createMock(PaymentProductHandlerInterface::class);
        $handler->method('getAvailableCountries')->willReturn([]);
        $handler->method('getAvailableCurrencies')->willReturn([]);

        $registry = $this->createMock(PaymentProductHandlerRegistryInterface::class);
        $registry->method('getForPaymentProduct')->willReturn($handler);

        $this->factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addType(new AlmaConfigurationType($countryRepo, $currencyRepo, $registry, $this->almaLimitsProvider))
            ->addType(new HostedPageProductConfigurationType())
            ->getFormFactory();
    }

    protected function tearDown(): void
    {
        Locale::setDefault($this->defaultLocale);
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function buildForm(
        ?string $accountCode = 'test-account',
        string $paymentProduct = 'alma-3x',
        ?array $data = null,
    ): FormInterface {
        return $this->factory->create(AlmaConfigurationType::class, $data, [
            'payment_product' => $paymentProduct,
            'account_code' => $accountCode,
        ]);
    }

    // -------------------------------------------------------------------------
    // Structure
    // -------------------------------------------------------------------------

    public function testFormHasMinimumAmountField(): void
    {
        $this->assertTrue($this->buildForm()->has('minimum_amount'));
    }

    public function testFormHasMaximumAmountField(): void
    {
        $this->assertTrue($this->buildForm()->has('maximum_amount'));
    }

    public function testMinimumAmountIsDisabled(): void
    {
        $this->assertTrue($this->buildForm()->get('minimum_amount')->isDisabled());
    }

    public function testMaximumAmountIsDisabled(): void
    {
        $this->assertTrue($this->buildForm()->get('maximum_amount')->isDisabled());
    }

    // -------------------------------------------------------------------------
    // Fetching the limits
    // -------------------------------------------------------------------------

    public function testSkipsApiCallWhenAccountCodeIsEmpty(): void
    {
        $this->almaLimitsProvider->expects($this->never())->method('getLimits');

        $this->buildForm(accountCode: '');
    }

    public function testFetchesLimitsWithTheAccountCodeAndPaymentProductOptions(): void
    {
        $this->almaLimitsProvider->expects($this->once())
            ->method('getLimits')
            ->with('my-account', 'alma-4x')
            ->willReturn(['min' => 50.0, 'max' => 2000.0]);

        $this->buildForm('my-account', 'alma-4x');
    }

    // -------------------------------------------------------------------------
    // PRE_SET_DATA: limits are the data, and are visible
    // -------------------------------------------------------------------------

    public function testLimitsAreInjectedAsData(): void
    {
        $this->almaLimitsProvider->method('getLimits')->willReturn(['min' => 30.0, 'max' => 2000.0]);

        $data = $this->buildForm()->getData();

        $this->assertEquals(3000, $data['minimum_amount']);
        $this->assertEquals(200000, $data['maximum_amount']);
    }

    public function testLimitsAreRenderedInTheView(): void
    {
        $this->almaLimitsProvider->method('getLimits')->willReturn(['min' => 30.0, 'max' => 2000.0]);

        $view = $this->buildForm()->createView();

        $this->assertSame('30.00', $view->children['minimum_amount']->vars['value']);
        $this->assertSame('2000.00', $view->children['maximum_amount']->vars['value']);
    }

    public function testLimitsOverrideThePersistedValues(): void
    {
        $this->almaLimitsProvider->method('getLimits')->willReturn(['min' => 30.0, 'max' => 2000.0]);

        $data = $this->buildForm(data: ['minimum_amount' => 999, 'maximum_amount' => 999])->getData();

        $this->assertEquals(3000, $data['minimum_amount']);
        $this->assertEquals(200000, $data['maximum_amount']);
    }

    public function testKeepsExistingValuesWhenApiReturnsNull(): void
    {
        $this->almaLimitsProvider->method('getLimits')->willReturn(null);

        $data = $this->buildForm(data: ['minimum_amount' => 999, 'maximum_amount' => 1999])->getData();

        $this->assertEquals(999, $data['minimum_amount']);
        $this->assertEquals(1999, $data['maximum_amount']);
    }

    // -------------------------------------------------------------------------
    // Submission: posted amounts must never win over the API ones
    // -------------------------------------------------------------------------

    public function testSubmittedAmountsAreIgnored(): void
    {
        $this->almaLimitsProvider->method('getLimits')->willReturn(['min' => 30.0, 'max' => 2000.0]);

        $form = $this->buildForm();
        $form->submit(['minimum_amount' => '1', 'maximum_amount' => '999999']);

        $data = $form->getData();
        $this->assertEquals(3000, $data['minimum_amount']);
        $this->assertEquals(200000, $data['maximum_amount']);
    }

    public function testAmountsStayEmptyOnSubmitWhenApiReturnsNull(): void
    {
        $this->almaLimitsProvider->method('getLimits')->willReturn(null);

        $form = $this->buildForm();
        $form->submit(['minimum_amount' => '1', 'maximum_amount' => '999999']);

        $data = $form->getData();
        $this->assertNull($data['minimum_amount'] ?? null);
        $this->assertNull($data['maximum_amount'] ?? null);
    }

    // -------------------------------------------------------------------------
    // Option propagation through the Hosted Page product wrapper
    // -------------------------------------------------------------------------

    public function testHostedPageProductWrapperForwardsTheAccountCode(): void
    {
        $this->almaLimitsProvider->expects($this->once())
            ->method('getLimits')
            ->with('wrapped-account', 'alma-3x')
            ->willReturn(['min' => 30.0, 'max' => 2000.0]);

        $form = $this->factory->create(HostedPageProductConfigurationType::class, null, [
            'payment_product' => 'alma-3x',
            'inner_type' => AlmaConfigurationType::class,
            'account_code' => 'wrapped-account',
        ]);

        $this->assertEquals(3000, $form->get('configuration')->getData()['minimum_amount']);
    }
}
