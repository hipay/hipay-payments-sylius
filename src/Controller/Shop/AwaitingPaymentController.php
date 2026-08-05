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

use HiPay\SyliusHiPayPlugin\Helper\AwaitingPaymentHelper;
use HiPay\SyliusHiPayPlugin\PaymentProduct\PluginPaymentProduct;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

final class AwaitingPaymentController
{
    /** @param OrderRepositoryInterface<OrderInterface> $orderRepository */
    public function __construct(
        private readonly Environment $twig,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly int $pollIntervalSeconds,
        private readonly int $expirationSeconds,
    ) {
    }

    public function index(string $tokenValue): Response
    {
        $order = $this->orderRepository->findOneByTokenValue($tokenValue);
        if (!$order instanceof OrderInterface || BaseOrderInterface::STATE_CART === $order->getState() || !$this->isCurrentUserOwner($order)) {
            return new RedirectResponse($this->urlGenerator->generate('sylius_shop_homepage'));
        }

        $payment = $order->getLastPayment();
        $paymentProduct = AwaitingPaymentHelper::resolvePaymentProduct($payment);
        if (null === $paymentProduct) { // Not mobile payment product, so we redirect to homepage
            return new RedirectResponse($this->urlGenerator->generate('sylius_shop_homepage'));
        }

        return new Response($this->twig->render(
            '@SyliusHiPayPlugin/shop/order/awaiting_payment/index.html.twig',
            [
                'order' => $order,
                'payment_product' => $paymentProduct,
                'poll_interval' => $this->pollIntervalSeconds,
                'expiration' => $this->expirationSeconds,
                'status_url' => $this->urlGenerator->generate(
                    'sylius_hipay_plugin_awaiting_payment_status',
                    ['tokenValue' => $tokenValue],
                ),
                'success_url' => $this->urlGenerator->generate('sylius_shop_order_thank_you'),
                'failure_url' => $this->urlGenerator->generate(
                    'sylius_shop_order_show',
                    ['tokenValue' => $tokenValue],
                ),
            ],
        ));
    }

    public function status(Request $request, string $tokenValue): JsonResponse
    {
        $order = $this->orderRepository->findOneByTokenValue($tokenValue);
        if (!$order instanceof OrderInterface || BaseOrderInterface::STATE_CART === $order->getState() || !$this->isCurrentUserOwner($order)) {
            return new JsonResponse(['status' => 'error'], Response::HTTP_NOT_FOUND);
        }

        $payment = $order->getLastPayment();
        $state = $payment?->getState() ?? PaymentInterface::STATE_UNKNOWN;
        $details = $payment?->getDetails() ?? [];

        $isMultibanco = PluginPaymentProduct::MULTIBANCO->value === ($details['payment_product'] ?? null);

        $currentFailed = in_array($state, AwaitingPaymentHelper::FAILURE_STATES, true);
        $currentSucceeded = in_array($state, AwaitingPaymentHelper::SUCCESS_STATES, true);

        $isFailure = $currentFailed ||
            (!$currentSucceeded && !$isMultibanco && $this->hasFailedPayment($order, $payment));
        $isSuccess = !$isFailure && ($currentSucceeded || $isMultibanco);

        if ($isFailure) {
            $this->addFailureFlash($request);
        }

        return new JsonResponse([
            'status' => $state,
            'is_success' => $isSuccess,
            'is_failure' => $isFailure,
        ]);
    }

    private function addFailureFlash(Request $request): void
    {
        $session = $request->getSession();
        if (!$session instanceof FlashBagAwareSessionInterface) {
            return;
        }

        $flashBag = $session->getFlashBag();
        $flashBag->add('error', 'hipay.payment.cancelled');
    }

    private function hasFailedPayment(OrderInterface $order, ?PaymentInterface $current): bool
    {
        foreach ($order->getPayments() as $payment) {
            if ($payment === $current) {
                continue;
            }

            if (in_array($payment->getState(), AwaitingPaymentHelper::FAILURE_STATES, true)) {
                return true;
            }
        }

        return false;
    }

    private function isCurrentUserOwner(OrderInterface $order): bool
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        // Anonymous users: the unguessable tokenValue is the sole gate (same as sylius_shop_order_show).
        if (null === $user) {
            return true;
        }

        // Logged-in users: additionally verify they own the order.
        $customer = $order->getCustomer();
        if (!$customer instanceof CustomerInterface) {
            return true;
        }

        return $customer->getEmail() === $user->getUserIdentifier();
    }
}
