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

namespace HiPay\SyliusHiPayPlugin\EventSubscriber\Shop;

use HiPay\SyliusHiPayPlugin\Payment\OrphanPaymentCancellerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Defence-in-depth cleanup: cancel orphan "new" HiPay payments before the
 * repayment page is rendered.
 *
 * Layers 1 (pessimistic lock in NotificationProcessor) and 2 (cleanup in
 * HostedFieldsHttpResponseProvider) should already prevent duplicate "new"
 * payments. This subscriber acts as a safety net: if, for any reason, the
 * order still has multiple "new" HiPay payments when the user loads the
 * repayment page, we cancel the orphans so only one payment form is shown.
 *
 * **Scope:**
 * Only fires on `sylius_shop_order_show` route (the repayment page).
 * Only affects HiPay payments — other gateways are never touched.
 */
final readonly class OrphanPaymentCleanupSubscriber implements EventSubscriberInterface
{
    /** @param OrderRepositoryInterface<OrderInterface> $orderRepository */
    public function __construct(
        private OrphanPaymentCancellerInterface $orphanPaymentCanceller,
        private OrderRepositoryInterface $orderRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ('sylius_shop_order_show' !== $request->attributes->get('_route')) {
            return;
        }

        /** @var string|null $tokenValue */
        $tokenValue = $request->attributes->get('tokenValue');
        if (null === $tokenValue || '' === $tokenValue) {
            return;
        }

        /** @var OrderInterface|null $order */
        $order = $this->orderRepository->findOneByTokenValue($tokenValue);
        if (null === $order) {
            return;
        }

        $this->orphanPaymentCanceller->cancelOrphanPayments($order);
    }
}
