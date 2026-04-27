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

namespace HiPay\SyliusHiPayPlugin\Entity;

use HiPay\SyliusHiPayPlugin\PaymentProduct\Oney\OneyStandardCategory;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;

interface OneyCategoryInterface extends ResourceInterface, TimestampableInterface
{
    public function getTaxon(): ?TaxonInterface;

    public function setTaxon(?TaxonInterface $taxon): void;

    public function getOneyCategory(): ?OneyStandardCategory;

    public function setOneyCategory(?OneyStandardCategory $oneyCategory): void;
}
