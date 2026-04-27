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
use Sylius\RefundPlugin\StateResolver\OrderPartiallyRefundedStateResolverInterface;
use Webmozart\Assert\Assert;

class OrderPartiallyRefundedStateResolver implements OrderPartiallyRefundedStateResolverInterface
{
    public function __construct(
        private OrderPartiallyRefundedStateResolverInterface $innerResolver,
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

        if ($order->getPaymentState() === OrderPaymentStates::STATE_PARTIALLY_REFUNDED) {
            return;
        }

        if (true === GatewayProvider::isHiPayGateway($order->getLastPayment()?->getMethod())) {
            return;
        }

        $this->innerResolver->resolve($orderNumber);
    }
}
