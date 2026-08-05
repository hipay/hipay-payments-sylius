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

namespace HiPay\SyliusHiPayPlugin\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class CancelPaymentAction
{
    /** @param OrderRepositoryInterface<OrderInterface> $orderRepository */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly StateMachineInterface $stateMachine,
        private readonly EntityManagerInterface $entityManager,
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function __invoke(Request $request, string $tokenValue): RedirectResponse
    {
        unset($request);

        /** @var OrderInterface|null $order */
        $order = $this->orderRepository->findOneByTokenValue($tokenValue);

        if (null === $order) {
            throw new NotFoundHttpException(sprintf('Order with token "%s" not found.', $tokenValue));
        }

        $this->assertCurrentUserOwnsOrder($order);

        $orderShowUrl = $this->router->generate('sylius_shop_order_show', ['tokenValue' => $tokenValue]);

        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);
        if (null === $payment || !$this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL)) {
            return new RedirectResponse($orderShowUrl);
        }

        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);
        $this->entityManager->flush();

        // @phpstan-ignore-next-line
        $this->requestStack->getSession()->getFlashBag()->add(
            'info',
            'hipay.payment.cancelled',
        );

        return new RedirectResponse($orderShowUrl);
    }

    private function assertCurrentUserOwnsOrder(OrderInterface $order): void
    {
        $token = $this->tokenStorage->getToken();
        /** @var ShopUserInterface|null $user */
        $user = $token?->getUser();

        // Anonymous users: the unguessable tokenValue is the sole gate (same as sylius_shop_order_show).
        if (null === $user) {
            return;
        }

        $orderCustomer = $order->getCustomer();
        if (null !== $orderCustomer && $user->getCustomer()?->getId() !== $orderCustomer->getId()) {
            throw new AccessDeniedHttpException('You are not allowed to cancel this order.');
        }
    }
}
