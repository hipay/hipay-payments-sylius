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

namespace Tests\HiPay\SyliusHiPayPlugin\Behat\Element\Admin\PaymentMethod;

use Behat\Mink\Exception\ElementNotFoundException;
use function is_string;
use Sylius\Behat\Element\Admin\Crud\FormElement as BaseFormElement;

final class GatewayConfigurationFormElement extends BaseFormElement implements GatewayConfigurationFormElementInterface
{
    public function selectAccount(string $code): void
    {
        $container = $this->getElement('account_container');
        $select = $container->find('css', 'select');
        if (null === $select) {
            throw new ElementNotFoundException($this->getSession(), 'select', 'css', 'select');
        }
        $select->selectOption($code);
    }

    public function selectPaymentProduct(string $product): void
    {
        if (!$this->hasElement('payment_product_container')) {
            throw new ElementNotFoundException($this->getSession(), 'payment product container', 'css', '[data-test-gateway-config-payment-product]');
        }
        $container = $this->getElement('payment_product_container');
        $select = $container->find('css', 'select');
        if (null === $select) {
            throw new ElementNotFoundException($this->getSession(), 'select', 'css', 'select');
        }
        $select->selectOption($product);
    }

    public function setTextColor(string $color): void
    {
        $this->getElement('text_color')->setValue($color);
    }

    public function checkCardBrand(string $brand): void
    {
        $checkbox = $this->getElement('allowed_brand', ['%brand%' => $brand]);
        if (!$checkbox->isChecked()) {
            $checkbox->check();
        }
    }

    public function uncheckCardBrand(string $brand): void
    {
        $checkbox = $this->getElement('allowed_brand', ['%brand%' => $brand]);
        if ($checkbox->isChecked()) {
            $checkbox->uncheck();
        }
    }

    public function getAccountValue(): ?string
    {
        $hidden = $this->getDocument()->find('css', 'input[data-test-gateway-config-account-value]');
        if (null !== $hidden) {
            $value = $hidden->getValue();

            return is_string($value) ? $value : null;
        }
        if ($this->hasElement('account_container')) {
            $container = $this->getElement('account_container');
            $select = $container->find('css', 'select');
            if (null !== $select) {
                $value = $select->getValue();

                return is_string($value) ? $value : null;
            }
        }

        return null;
    }

    public function getPaymentProductValue(): ?string
    {
        $hidden = $this->getDocument()->find('css', 'input[data-test-gateway-config-payment-product-value]');
        if (null !== $hidden) {
            $value = $hidden->getValue();

            return is_string($value) ? $value : null;
        }
        if ($this->hasElement('payment_product_container')) {
            $container = $this->getElement('payment_product_container');
            $select = $container->find('css', 'select');
            if (null !== $select) {
                $value = $select->getValue();

                return is_string($value) ? $value : null;
            }
        }

        return null;
    }

    public function getTextColorValue(): ?string
    {
        if (!$this->hasElement('text_color')) {
            return null;
        }

        $value = $this->getElement('text_color')->getValue();

        return is_string($value) ? $value : null;
    }

    public function isCardBrandChecked(string $brand): bool
    {
        if (!$this->hasElement('allowed_brand', ['%brand%' => $brand])) {
            return false;
        }

        return $this->getElement('allowed_brand', ['%brand%' => $brand])->isChecked();
    }

    /**
     * @return array<string, string>
     */
    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'account_container' => '[data-test-gateway-config-account]',
            'allowed_brand' => '[data-test-gateway-config-allowed-brand="%brand%"]',
            'payment_product_container' => '[data-test-gateway-config-payment-product]',
            'text_color' => '[data-test-gateway-config-text-color]',
        ]);
    }
}
