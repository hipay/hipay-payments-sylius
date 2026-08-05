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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Controller\Shop\CancelPaymentAction;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class CancelPaymentActionTest extends TestCase
{
    private OrderRepositoryInterface $orderRepository;

    private StateMachineInterface $stateMachine;

    private EntityManagerInterface $entityManager;

    private RouterInterface $router;

    private FlashBagInterface $flashBag;

    private TokenStorageInterface $tokenStorage;

    private CancelPaymentAction $action;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->router = $this->createMock(RouterInterface::class);

        $this->flashBag = $this->createMock(FlashBagInterface::class);
        $session = $this->createMock(Session::class);
        $session->method('getFlashBag')->willReturn($this->flashBag);
        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getSession')->willReturn($session);

        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);

        $this->action = new CancelPaymentAction(
            $this->orderRepository,
            $this->stateMachine,
            $this->entityManager,
            $this->router,
            $requestStack,
            $this->tokenStorage,
        );
    }

    public function testThrows404WhenOrderNotFound(): void
    {
        $this->orderRepository->method('findOneByTokenValue')->with('unknown-token')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        ($this->action)(new Request(), 'unknown-token');
    }

    public function testRedirectsToOrderShowWhenNoProcessingPayment(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->with(PaymentInterface::STATE_PROCESSING)->willReturn(null);

        $this->orderRepository->method('findOneByTokenValue')->willReturn($order);
        $this->router->method('generate')
            ->with('sylius_shop_order_show', ['tokenValue' => 'abc'])
            ->willReturn('/order/abc');

        $this->entityManager->expects($this->never())->method('flush');

        $response = ($this->action)(new Request(), 'abc');

        $this->assertSame('/order/abc', $response->getTargetUrl());
    }

    public function testRedirectsToOrderShowWhenCancelTransitionNotPossible(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->with(PaymentInterface::STATE_PROCESSING)->willReturn($payment);

        $this->orderRepository->method('findOneByTokenValue')->willReturn($order);
        $this->stateMachine->method('can')
            ->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL)
            ->willReturn(false);
        $this->router->method('generate')
            ->with('sylius_shop_order_show', ['tokenValue' => 'abc'])
            ->willReturn('/order/abc');

        $this->entityManager->expects($this->never())->method('flush');

        $response = ($this->action)(new Request(), 'abc');

        $this->assertSame('/order/abc', $response->getTargetUrl());
    }

    public function testCancelsPaymentAndRedirectsToOrderShow(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->with(PaymentInterface::STATE_PROCESSING)->willReturn($payment);

        $this->orderRepository->method('findOneByTokenValue')->willReturn($order);
        $this->stateMachine->method('can')
            ->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL)
            ->willReturn(true);
        $this->stateMachine->expects($this->once())
            ->method('apply')
            ->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);
        $this->entityManager->expects($this->once())->method('flush');
        $this->router->method('generate')
            ->with('sylius_shop_order_show', ['tokenValue' => 'abc'])
            ->willReturn('/order/abc');
        $this->flashBag->expects($this->once())
            ->method('add')
            ->with('info', 'hipay.payment.cancelled');

        $response = ($this->action)(new Request(), 'abc');

        $this->assertSame('/order/abc', $response->getTargetUrl());
    }

    public function testNoFlashWhenNoProcessingPayment(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->with(PaymentInterface::STATE_PROCESSING)->willReturn(null);

        $this->orderRepository->method('findOneByTokenValue')->willReturn($order);
        $this->router->method('generate')->willReturn('/order/abc');
        $this->flashBag->expects($this->never())->method('add');

        ($this->action)(new Request(), 'abc');
    }

    public function testThrows403WhenLoggedInUserIsNotOrderOwner(): void
    {
        $orderCustomer = $this->createMock(CustomerInterface::class);
        $orderCustomer->method('getId')->willReturn(1);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCustomer')->willReturn($orderCustomer);
        $order->method('getLastPayment')->willReturn(null);

        $this->orderRepository->method('findOneByTokenValue')->willReturn($order);

        $userCustomer = $this->createMock(CustomerInterface::class);
        $userCustomer->method('getId')->willReturn(2);

        $shopUser = $this->createMock(ShopUserInterface::class);
        $shopUser->method('getCustomer')->willReturn($userCustomer);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($shopUser);

        $this->tokenStorage->method('getToken')->willReturn($token);

        $this->expectException(AccessDeniedHttpException::class);

        ($this->action)(new Request(), 'abc');
    }

    public function testAllowsWhenLoggedInUserIsOrderOwner(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(42);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getCustomer')->willReturn($customer);
        $order->method('getLastPayment')->with(PaymentInterface::STATE_PROCESSING)->willReturn(null);

        $this->orderRepository->method('findOneByTokenValue')->willReturn($order);
        $this->router->method('generate')->willReturn('/order/abc');

        $shopUser = $this->createMock(ShopUserInterface::class);
        $shopUser->method('getCustomer')->willReturn($customer);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($shopUser);

        $this->tokenStorage->method('getToken')->willReturn($token);

        $response = ($this->action)(new Request(), 'abc');

        $this->assertSame('/order/abc', $response->getTargetUrl());
    }
}
