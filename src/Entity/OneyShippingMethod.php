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

use HiPay\SyliusHiPayPlugin\Form\Type\Resource\OneyShippingMethodType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Oney\OneyStandardShippingMethod;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\Resource\Metadata\AsResource;
use Sylius\Resource\Metadata\BulkDelete;
use Sylius\Resource\Metadata\Create;
use Sylius\Resource\Metadata\Delete;
use Sylius\Resource\Metadata\Index;
use Sylius\Resource\Metadata\Update;
use Sylius\Resource\Model\TimestampableTrait;

#[AsResource(
    alias: 'sylius_hipay_plugin.oney_shipping_method',
    section: 'admin',
    formType: OneyShippingMethodType::class,
    templatesDir: '@SyliusAdmin/shared/crud',
    routePrefix: '/hipay',
    name: 'oney_shipping_method',
    pluralName: 'oney_shipping_methods',
    applicationName: 'sylius_hipay_plugin',
    vars: [
        'subheader' => 'sylius_hipay_plugin.ui.manage_oney_shipping_methods',
    ],
    operations: [
        new Index(
            vars: ['header' => 'sylius_hipay_plugin.ui.oney_shipping_methods'],
            grid: 'hipay_admin_oney_shipping_method',
        ),
        new Create(
            redirectToRoute: 'sylius_hipay_plugin_admin_oney_shipping_method_index',
        ),
        new Update(
            redirectToRoute: 'sylius_hipay_plugin_admin_oney_shipping_method_index',
        ),
        new Delete(),
        new BulkDelete(),
    ],
)]
class OneyShippingMethod implements OneyShippingMethodInterface
{
    use TimestampableTrait;

    private ?int $id = null;

    private ?ShippingMethodInterface $shippingMethod = null;

    private ?OneyStandardShippingMethod $oneyShippingMethod = null;

    private int $oneyPreparationTime = 0;

    private int $oneyDeliveryTime = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShippingMethod(): ?ShippingMethodInterface
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(?ShippingMethodInterface $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }

    public function getOneyShippingMethod(): ?OneyStandardShippingMethod
    {
        return $this->oneyShippingMethod;
    }

    public function setOneyShippingMethod(?OneyStandardShippingMethod $oneyShippingMethod): void
    {
        $this->oneyShippingMethod = $oneyShippingMethod;
    }

    public function getOneyPreparationTime(): int
    {
        return $this->oneyPreparationTime;
    }

    public function setOneyPreparationTime(int $oneyPreparationTime): void
    {
        $this->oneyPreparationTime = $oneyPreparationTime;
    }

    public function getOneyDeliveryTime(): int
    {
        return $this->oneyDeliveryTime;
    }

    public function setOneyDeliveryTime(int $oneyDeliveryTime): void
    {
        $this->oneyDeliveryTime = $oneyDeliveryTime;
    }
}
