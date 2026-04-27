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

namespace Tests\HiPay\SyliusHiPayPlugin\Behat\Element\Admin\OneyCategory;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Sylius\Behat\Element\Admin\Crud\FormElement as BaseFormElement;

class FormElement extends BaseFormElement implements FormElementInterface
{
    public function selectTaxonByName(string $taxonName): void
    {
        $this->getElement('taxon')->selectOption($taxonName);
    }

    public function selectOneyStandardCategoryByLabel(string $label): void
    {
        $this->getElement('oney_category')->selectOption($label);
    }

    public function getSelectableTaxonNames(): array
    {
        $select = $this->getTaxonSelectElement();
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
            'taxon' => '[data-test-taxon]',
            'oney_category' => '[data-test-oney-category]',
        ]);
    }

    private function getTaxonSelectElement(): NodeElement
    {
        $node = $this->getElement('taxon');
        if ('select' === $node->getTagName()) {
            return $node;
        }

        $select = $node->find('css', 'select');
        if (null === $select) {
            throw new ElementNotFoundException($this->getSession(), 'taxon select', 'css', '[data-test-taxon] select');
        }

        return $select;
    }
}
