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

namespace Tests\HiPay\SyliusHiPayPlugin\Behat\Element\Admin\OneyShippingMethod;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Sylius\Behat\Element\Admin\Crud\FormElement as BaseFormElement;

class FormElement extends BaseFormElement implements FormElementInterface
{
    public function selectShippingMethodByName(string $name): void
    {
        $this->getShippingMethodSelectElement()->selectOption($name);
    }

    public function selectOneyStandardShippingMethodByLabel(string $label): void
    {
        $this->getOneyShippingMethodSelectElement()->selectOption($label);
    }

    public function setOneyPreparationTime(int $value): void
    {
        $this->setIntegerFieldValue('oney_preparation_time', (string) $value);
    }

    public function setOneyDeliveryTime(int $value): void
    {
        $this->setIntegerFieldValue('oney_delivery_time', (string) $value);
    }

    public function getSelectableShippingMethodNames(): array
    {
        $select = $this->getShippingMethodSelectElement();
        $names = [];
        foreach ($select->findAll('css', 'option') as $option) {
            if ('' === $option->getValue()) {
                continue;
            }
            $names[] = trim($option->getText());
        }

        return array_values(array_unique($names));
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'shipping_method' => '[data-test-shipping-method]',
            'oney_shipping_method' => '[data-test-oney-shipping-method]',
            'oney_preparation_time' => '[data-test-oney-preparation-time]',
            'oney_delivery_time' => '[data-test-oney-delivery-time]',
        ]);
    }

    private function getShippingMethodSelectElement(): NodeElement
    {
        return $this->getSelectInsideTestAttribute('shipping_method', 'shipping method');
    }

    private function getOneyShippingMethodSelectElement(): NodeElement
    {
        return $this->getSelectInsideTestAttribute('oney_shipping_method', 'Oney shipping method');
    }

    private function getSelectInsideTestAttribute(string $elementKey, string $description): NodeElement
    {
        $node = $this->getElement($elementKey);
        if ('select' === $node->getTagName()) {
            return $node;
        }

        $select = $node->find('css', 'select');
        if (null === $select) {
            throw new ElementNotFoundException($this->getSession(), $description . ' select', 'css', '[data-test-' . str_replace('_', '-', $elementKey) . '] select');
        }

        return $select;
    }

    private function setIntegerFieldValue(string $elementName, string $value): void
    {
        $node = $this->getElement($elementName);
        $input = 'input' === $node->getTagName() ? $node : $node->find('css', 'input');
        if (null === $input) {
            throw new ElementNotFoundException($this->getSession(), 'integer input', 'css', sprintf('[data-test-%s] input', str_replace('_', '-', $elementName)));
        }

        $input->setValue($value);
    }
}
