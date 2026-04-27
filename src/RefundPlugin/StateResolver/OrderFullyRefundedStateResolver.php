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

namespace HiPay\SyliusHiPayPlugin\RefundPlugin\StateResolver;

use HiPay\SyliusHiPayPlugin\Provider\GatewayProvider;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\RefundPlugin\Checker\OrderFullyRefundedTotalCheckerInterface;
use Sylius\RefundPlugin\StateResolver\OrderFullyRefundedStateResolverInterface;
use Webmozart\Assert\Assert;

class OrderFullyRefundedStateResolver implements OrderFullyRefundedStateResolverInterface
{
    public function __construct(
        private OrderFullyRefundedStateResolverInterface $innerResolver,
        private OrderFullyRefundedTotalCheckerInterface $orderFullyRefundedTotalChecker,
        private EntityRepository $orderRepository,
    ) {
    }

    public function resolve(string $orderNumber): void
    {
        /**
         * @var OrderInterface $order
         *
         * @phpstan-ignore-next-line
         */
        $order = $this->orderRepository->findOneByNumber($orderNumber);
        Assert::notNull($order);

        if (!$this->orderFullyRefundedTotalChecker->isOrderFullyRefunded($order) || OrderPaymentStates::STATE_REFUNDED === $order->getPaymentState()) {
            return;
        }
        if (true === GatewayProvider::isHiPayGateway($order->getLastPayment()?->getMethod())) {
            return;
        }

        $this->innerResolver->resolve($orderNumber);
    }
}
