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

use HiPay\SyliusHiPayPlugin\PaymentProduct\Oney\OneyStandardShippingMethod;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;

interface OneyShippingMethodInterface extends ResourceInterface, TimestampableInterface
{
    public function getShippingMethod(): ?ShippingMethodInterface;

    public function setShippingMethod(?ShippingMethodInterface $shippingMethod): void;

    public function getOneyShippingMethod(): ?OneyStandardShippingMethod;

    public function setOneyShippingMethod(?OneyStandardShippingMethod $oneyShippingMethod): void;

    public function getOneyPreparationTime(): int;

    public function setOneyPreparationTime(int $oneyPreparationTime): void;

    public function getOneyDeliveryTime(): int;

    public function setOneyDeliveryTime(int $oneyDeliveryTime): void;
}
