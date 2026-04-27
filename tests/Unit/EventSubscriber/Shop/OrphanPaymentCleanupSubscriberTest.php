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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\EventSubscriber\Shop;

use HiPay\SyliusHiPayPlugin\EventSubscriber\Shop\OrphanPaymentCleanupSubscriber;
use HiPay\SyliusHiPayPlugin\Payment\OrphanPaymentCancellerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @covers \HiPay\SyliusHiPayPlugin\EventSubscriber\Shop\OrphanPaymentCleanupSubscriber
 */
final class OrphanPaymentCleanupSubscriberTest extends TestCase
{
    private OrphanPaymentCancellerInterface&MockObject $canceller;

    private OrderRepositoryInterface&MockObject $orderRepository;

    private OrphanPaymentCleanupSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->canceller = $this->createMock(OrphanPaymentCancellerInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);

        $this->subscriber = new OrphanPaymentCleanupSubscriber(
            $this->canceller,
            $this->orderRepository,
        );
    }

    public function testSubscribesToKernelControllerEvent(): void
    {
        $events = OrphanPaymentCleanupSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::CONTROLLER, $events);
    }

    public function testCleansUpOrphansOnOrderShowRoute(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $this->orderRepository->method('findOneByTokenValue')->with('abc123')->willReturn($order);

        $this->canceller->expects($this->once())
            ->method('cancelOrphanPayments')
            ->with($order);

        $event = $this->createControllerEvent('sylius_shop_order_show', 'abc123', true);
        $this->subscriber->onKernelController($event);
    }

    public function testSkipsNonMainRequests(): void
    {
        $this->canceller->expects($this->never())->method('cancelOrphanPayments');

        $event = $this->createControllerEvent('sylius_shop_order_show', 'abc123', false);
        $this->subscriber->onKernelController($event);
    }

    public function testSkipsOtherRoutes(): void
    {
        $this->canceller->expects($this->never())->method('cancelOrphanPayments');

        $event = $this->createControllerEvent('sylius_shop_homepage', null, true);
        $this->subscriber->onKernelController($event);
    }

    public function testSkipsWhenTokenValueIsEmpty(): void
    {
        $this->canceller->expects($this->never())->method('cancelOrphanPayments');

        $event = $this->createControllerEvent('sylius_shop_order_show', '', true);
        $this->subscriber->onKernelController($event);
    }

    public function testSkipsWhenOrderNotFound(): void
    {
        $this->orderRepository->method('findOneByTokenValue')->willReturn(null);

        $this->canceller->expects($this->never())->method('cancelOrphanPayments');

        $event = $this->createControllerEvent('sylius_shop_order_show', 'nonexistent', true);
        $this->subscriber->onKernelController($event);
    }

    private function createControllerEvent(string $route, ?string $tokenValue, bool $isMainRequest): ControllerEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);
        if (null !== $tokenValue) {
            $request->attributes->set('tokenValue', $tokenValue);
        }

        $kernel = $this->createMock(HttpKernelInterface::class);

        return new ControllerEvent(
            $kernel,
            static fn () => null,
            $request,
            $isMainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
        );
    }
}
