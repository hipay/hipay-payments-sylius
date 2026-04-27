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

use Sylius\Behat\Element\Admin\Crud\FormElementInterface as BaseFormElementInterface;

interface FormElementInterface extends BaseFormElementInterface
{
    public function selectTaxonByName(string $taxonName): void;

    public function selectOneyStandardCategoryByLabel(string $label): void;

    /**
     * @return list<string> Visible labels for real choices (excludes empty placeholder option).
     */
    public function getSelectableTaxonNames(): array;
}
