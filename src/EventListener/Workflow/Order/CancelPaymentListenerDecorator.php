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

namespace HiPay\SyliusHiPayPlugin\EventListener\Workflow\Order;

use HiPay\SyliusHiPayPlugin\Provider\GatewayProvider;
use Sylius\Bundle\CoreBundle\EventListener\Workflow\Order\CancelPaymentListener;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Decorates {@see CancelPaymentListener} (service id: sylius.listener.workflow.order.cancel_payment).
 */
final class CancelPaymentListenerDecorator
{
    public function __construct(
        private readonly CancelPaymentListener $inner,
    ) {
    }

    public function __invoke(CompletedEvent $event): void
    {
        $order = $event->getSubject();

        if (!$order instanceof OrderInterface) {
            return;
        }
        $lastPayment = $order->getLastPayment();
        $paymentMethod = $lastPayment?->getMethod();
        if (true === GatewayProvider::isHiPayGateway($paymentMethod)) {
            return;
        }
        ($this->inner)($event);
    }
}
