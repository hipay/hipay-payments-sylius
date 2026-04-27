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

use HiPay\SyliusHiPayPlugin\Form\Type\Resource\OneyCategoryType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Oney\OneyStandardCategory;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\BulkDelete;
use Sylius\Resource\Metadata\Create;
use Sylius\Resource\Metadata\Delete;
use Sylius\Resource\Metadata\Index;
use Sylius\Resource\Metadata\Update;
use Sylius\Resource\Model\TimestampableTrait;

#[AsResource(
    alias: 'sylius_hipay_plugin.oney_category',
    section: 'admin',
    formType: OneyCategoryType::class,
    templatesDir: '@SyliusAdmin/shared/crud',
    routePrefix: '/hipay',
    name: 'oney_category',
    pluralName: 'oney_categories',
    applicationName: 'sylius_hipay_plugin',
    vars: [
        'subheader' => 'sylius_hipay_plugin.ui.manage_oney_categories',
    ],
    operations: [
        new Index(
            vars: ['header' => 'sylius_hipay_plugin.ui.oney_categories'],
            grid: 'hipay_admin_oney_category',
        ),
        new Create(
            redirectToRoute: 'sylius_hipay_plugin_admin_oney_category_index',
        ),
        new Update(
            redirectToRoute: 'sylius_hipay_plugin_admin_oney_category_index',
        ),
        new Delete(),
        new BulkDelete(),
    ],
)]
class OneyCategory implements OneyCategoryInterface
{
    use TimestampableTrait;

    private ?int $id = null;

    private ?TaxonInterface $taxon = null;

    private ?OneyStandardCategory $oneyCategory = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaxon(): ?TaxonInterface
    {
        return $this->taxon;
    }

    public function setTaxon(?TaxonInterface $taxon): void
    {
        $this->taxon = $taxon;
    }

    public function getOneyCategory(): ?OneyStandardCategory
    {
        return $this->oneyCategory;
    }

    public function setOneyCategory(?OneyStandardCategory $oneyCategory): void
    {
        $this->oneyCategory = $oneyCategory;
    }
}
