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

namespace HiPay\SyliusHiPayPlugin\Payment;

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Provider\GatewayProvider;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;

/**
 * Cancels orphan "new" HiPay payments on an order, keeping only the most recent one.
 *
 * **Problem:**
 * When a HiPay payment fails (e.g. iDEAL cancellation), two concurrent requests
 * can arrive at the same instant — the browser after-pay redirect and the HiPay
 * webhook notification. Without a DB-level lock both threads read the same
 * payment state, both apply the `fail` transition, and both trigger Sylius's
 * `OrderProcessor` which creates a new `Payment` for the retry. The result is
 * two `Payment` entities in `new` state on the same order, which renders two
 * payment forms on the repayment page.
 *
 * **Fix:**
 * This service is called after webhook/after-pay processing and before rendering
 * the repayment page. It finds all `new`-state payments whose method uses a
 * HiPay gateway, keeps the one with the highest ID (most recent), and transitions
 * the others to `cancelled`.
 *
 * **Scope:**
 * Only HiPay payments are affected — payments for other gateways (PayPal,
 * Stripe, manual, etc.) are never touched.
 */
final readonly class OrphanPaymentCanceller implements OrphanPaymentCancellerInterface
{
    public function __construct(
        private StateMachineInterface $stateMachine,
        private EntityManagerInterface $entityManager,
        private HiPayLoggerInterface $logger,
    ) {
    }

    /**
     * Cancel all but the most recent "new" HiPay payment on the given order.
     *
     * @return int Number of orphan payments cancelled
     */
    public function cancelOrphanPayments(OrderInterface $order): int
    {
        /** @var PaymentInterface[] $hiPayNewPayments */
        $hiPayNewPayments = [];
        foreach ($order->getPayments() as $payment) {
            if (PaymentInterface::STATE_NEW === $payment->getState() &&
                GatewayProvider::isHiPayGateway($payment->getMethod())
            ) {
                $hiPayNewPayments[] = $payment;
            }
        }

        if (count($hiPayNewPayments) <= 1) {
            return 0;
        }

        // Sort descending by ID — the highest ID is the most recently created
        // and therefore the legitimate payment to keep.
        usort($hiPayNewPayments, static fn ($left, $right): int => $right->getId() <=> $left->getId());

        // The first element is the keeper; the rest are orphans.
        array_shift($hiPayNewPayments);

        $cancelled = 0;

        foreach ($hiPayNewPayments as $orphan) {
            if (false === $this->stateMachine->can($orphan, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL)) {
                $this->logger->warning('[HiPay][OrphanPaymentCanceller] Cannot cancel orphan payment — transition not available', [
                    'payment_id' => $orphan->getId(),
                    'state' => $orphan->getState(),
                    'order_number' => $order->getNumber(),
                ]);

                continue;
            }

            $this->stateMachine->apply($orphan, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);
            ++$cancelled;

            $this->logger->info('[HiPay][OrphanPaymentCanceller] Cancelled orphan "new" payment to prevent duplicate form', [
                'payment_id' => $orphan->getId(),
                'order_number' => $order->getNumber(),
            ]);
        }

        if ($cancelled > 0) {
            $this->entityManager->flush();
        }

        return $cancelled;
    }
}
