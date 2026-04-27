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

namespace HiPay\SyliusHiPayPlugin\Provider;

use HiPay\SyliusHiPayPlugin\Entity\OneyShippingMethodInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\OrderInterface;

final class OneyShippingMethodProvider implements OneyShippingMethodProviderInterface
{
    public function __construct(
        private readonly EntityRepository $oneyShippingMethodRepository,
    ) {
    }

    public function getByOrder(OrderInterface $order): ?OneyShippingMethodInterface
    {
        $shipment = $order->getShipments()->first();
        if (false === $shipment) {
            return null;
        }

        $shippingMethod = $shipment->getMethod();
        if (null === $shippingMethod) {
            return null;
        }

        /** @var OneyShippingMethodInterface|null $mapping */
        $mapping = $this->oneyShippingMethodRepository->findOneBy(['shippingMethod' => $shippingMethod]);

        return $mapping;
    }
}
