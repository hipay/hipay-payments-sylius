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

namespace HiPay\SyliusHiPayPlugin\PaymentOrderRequest;

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use InvalidArgumentException;
use RuntimeException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

readonly class PaymentOrderRequestContextFactory implements PaymentOrderRequestContextFactoryInterface
{
    public function __construct(private RepositoryInterface $accountRepository)
    {
    }

    public function buildFromPaymentRequest(PaymentRequestInterface $paymentRequest): PaymentOrderRequestContext
    {
        $payment = $paymentRequest->getPayment();
        if (!$payment instanceof PaymentInterface) {
            throw new InvalidArgumentException('Payment is not a core payment');
        }

        $order = $payment->getOrder();
        if (!$order instanceof OrderInterface) {
            throw new InvalidArgumentException('Order is not set');
        }

        $paymentMethod = $payment->getMethod();
        if (!$paymentMethod instanceof PaymentMethodInterface) {
            throw new InvalidArgumentException('Payment method is not set');
        }

        $accountCode = $payment->getMethod()?->getGatewayConfig()?->getConfig()['account'] ?? null;
        if (!is_string($accountCode)) {
            throw new InvalidArgumentException('Payment account is not set');
        }

        /** @var ?AccountInterface $account */
        $account = $this->accountRepository->findOneBy(['code' => $accountCode]);
        if (null === $account) {
            throw new RuntimeException(sprintf('Account with code "%s" not found', $accountCode));
        }

        $paymentProduct = $payment->getMethod()?->getGatewayConfig()?->getConfig()['payment_product'] ?? null;
        if (!is_string($paymentProduct)) {
            throw new InvalidArgumentException('Payment product is not set');
        }

        return new PaymentOrderRequestContext(
            order: $order,
            payment: $payment,
            paymentRequest: $paymentRequest,
            account: $account,
            paymentProduct: $paymentProduct,
            payload: $payment->getDetails(),
            gatewayConfig: $paymentMethod->getGatewayConfig()?->getConfig() ?? [],
            action: $paymentRequest->getAction(),
        );
    }
}
