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

namespace Tests\HiPay\SyliusHiPayPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Sylius\Behat\Page\Admin\PaymentMethod\CreatePageInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface as PayumGatewayConfigInterface;
use Sylius\Component\Core\Factory\PaymentMethodFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Tests\HiPay\SyliusHiPayPlugin\Behat\Element\Admin\PaymentMethod\GatewayConfigurationFormElementInterface;
use Webmozart\Assert\Assert;

final readonly class ManagingHiPayPaymentMethodContext implements Context
{
    /**
     * @param PaymentMethodRepositoryInterface<PaymentMethodInterface> $paymentMethodRepository
     * @param PaymentMethodFactoryInterface<PaymentMethodInterface>    $paymentMethodFactory
     */
    public function __construct(
        private CreatePageInterface $createPage,
        private GatewayConfigurationFormElementInterface $gatewayConfigElement,
        private SharedStorageInterface $sharedStorage,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private PaymentMethodFactoryInterface $paymentMethodFactory,
    ) {
    }

    /**
     * @Given there is a HiPay Hosted Fields payment method named :name with code :code using account :accountCode
     */
    public function thereIsAHiPayHostedFieldsPaymentMethodNamedWithCodeUsingAccount(
        string $name,
        string $code,
        string $accountCode,
    ): void {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $this->paymentMethodFactory->createWithGateway('hipay_hosted_fields');
        $paymentMethod->setCode($code);
        $paymentMethod->setName($name);
        $paymentMethod->setCurrentLocale('en_US');
        $paymentMethod->setFallbackLocale('en_US');

        $gatewayConfig = $paymentMethod->getGatewayConfig();
        Assert::notNull($gatewayConfig);
        Assert::isInstanceOf($gatewayConfig, PayumGatewayConfigInterface::class);
        $gatewayConfig->setGatewayName('hipay_hosted_fields');
        $gatewayConfig->setConfig([
            'account' => $accountCode,
            'payment_product' => 'card',
            'configuration' => [
                'capture_mode' => 'sale',
                'allowed_countries' => ['FR', 'US'],
                'allowed_currencies' => ['EUR', 'USD'],
                'allowed_brands' => ['visa', 'mastercard'],
                'one_click_enabled' => false,
                'three_ds_mode' => '3ds_if_available',
                'text_color' => '#000000',
                'placeholder_color' => '#999999',
                'icon_color' => '#00ADE9',
                'invalid_text_color' => '#D50000',
                'valid_text_color' => '#4CAF50',
                'oneclick_highlight_color' => '#00ADE9',
                'save_button_color' => '#00ADE9',
                'font_family' => 'Roboto, sans-serif',
                'font_size' => '15px',
                'font_style' => 'normal',
                'font_weight' => '400',
                'text_decoration' => 'none',
            ],
        ]);
        $gatewayConfig->setUsePayum(false);

        if ($this->sharedStorage->has('channel')) {
            /** @var ChannelInterface $channel */
            $channel = $this->sharedStorage->get('channel');
            $paymentMethod->addChannel($channel);
        }

        $this->paymentMethodRepository->add($paymentMethod);
        $this->sharedStorage->set('payment_method', $paymentMethod);
    }

    /**
     * @When I want to create a new HiPay Hosted Fields payment method
     */
    public function iWantToCreateANewHiPayHostedFieldsPaymentMethod(): void
    {
        $this->createPage->open(['factory' => 'hipay_hosted_fields']);
    }

    /**
     * @When I select HiPay account :code
     */
    public function iSelectHiPayAccount(string $code): void
    {
        $this->gatewayConfigElement->selectAccount($code);
    }

    /**
     * @When I select payment product :product
     */
    public function iSelectPaymentProduct(string $product): void
    {
        $this->gatewayConfigElement->selectPaymentProduct($product);
    }

    /**
     * @When I set gateway configuration text color to :color
     */
    public function iSetGatewayConfigurationTextColorTo(string $color): void
    {
        $this->gatewayConfigElement->setTextColor($color);
    }

    /**
     * @When I check the card brand :brand
     */
    public function iCheckTheCardBrand(string $brand): void
    {
        $this->gatewayConfigElement->checkCardBrand($brand);
    }

    /**
     * @When I uncheck the card brand :brand
     */
    public function iUncheckTheCardBrand(string $brand): void
    {
        $this->gatewayConfigElement->uncheckCardBrand($brand);
    }

    /**
     * @Then the gateway configuration account should be :code
     */
    public function theGatewayConfigurationAccountShouldBe(string $code): void
    {
        Assert::same($this->gatewayConfigElement->getAccountValue(), $code);
    }

    /**
     * @Then the gateway configuration payment product should be :product
     */
    public function theGatewayConfigurationPaymentProductShouldBe(string $product): void
    {
        Assert::same($this->gatewayConfigElement->getPaymentProductValue(), $product);
    }

    /**
     * @Then the gateway configuration text color should be :color
     */
    public function theGatewayConfigurationTextColorShouldBe(string $color): void
    {
        Assert::same($this->gatewayConfigElement->getTextColorValue(), $color);
    }

    /**
     * @Then the card brand :brand should be checked
     */
    public function theCardBrandShouldBeChecked(string $brand): void
    {
        Assert::true($this->gatewayConfigElement->isCardBrandChecked($brand));
    }
}
