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

namespace HiPay\SyliusHiPayPlugin\EventSubscriber\Admin;

use HiPay\SyliusHiPayPlugin\Processor\CancelProcessor;
use HiPay\SyliusHiPayPlugin\Provider\GatewayProvider;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Event\GuardEvent;

class OrderLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CancelProcessor $cancelProcessor,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.sylius_order.transition.cancel' => 'onOrderCancelled',
            'workflow.sylius_order.guard.cancel' => 'guardReviewCancellation',
        ];
    }

    public function onOrderCancelled(Event $event): void
    {
        $order = $event->getSubject();

        if (!$order instanceof OrderInterface) {
            return;
        }
        $lastPayment = $order->getLastPayment();
        $paymentMethod = $lastPayment?->getMethod();
        if (null === $lastPayment || false === GatewayProvider::isHiPayGateway($paymentMethod)) {
            return;
        }

        $this->cancelProcessor->cancel($lastPayment);
    }

    public function guardReviewCancellation(GuardEvent $event): void
    {
        $order = $event->getSubject();

        if (!$order instanceof OrderInterface) {
            return;
        }
        $lastPayment = $order->getLastPayment();
        $paymentMethod = $lastPayment?->getMethod();
        if (null === $lastPayment || false === GatewayProvider::isHiPayGateway($paymentMethod) || in_array($lastPayment->getState(), [PaymentInterface::STATE_AUTHORIZED, PaymentInterface::STATE_FAILED], true)) {
            return;
        }
        $event->setBlocked(true, 'This order has been already captured.');
    }
}
