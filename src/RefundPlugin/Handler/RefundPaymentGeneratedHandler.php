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

namespace HiPay\SyliusHiPayPlugin\RefundPlugin\Handler;

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Client\ClientProviderInterface;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Provider\AccountProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\GatewayProvider;
use HiPay\SyliusHiPayPlugin\Provider\PaymentRequestProviderInterface;
use HiPay\SyliusHiPayPlugin\Provider\TransactionProviderInterface;
use RuntimeException;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\RefundPlugin\Event\RefundPaymentGenerated;

class RefundPaymentGeneratedHandler
{
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly ClientProviderInterface $hiPayClientProvider,
        private readonly TransactionProviderInterface $hiPayTransactionProvider,
        private readonly AccountProviderInterface $accountProvider,
        private readonly HiPayLoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly PaymentRequestProviderInterface $paymentRequestProvider,
    ) {
    }

    public function __invoke(RefundPaymentGenerated $message): void
    {
        $orderNumber = $message->orderNumber();
        // @phpstan-ignore-next-line
        $order = $this->orderRepository->findOneByNumber($orderNumber);
        if (!$order instanceof OrderInterface) {
            $this->logger->warning('Order not found for the refund payment.', ['orderNumber' => $orderNumber]);

            return;
        }
        $payment = $order->getLastPayment();
        if (null === $payment) {
            $this->logger->warning('Payment for this order not found.', ['orderNumber' => $orderNumber]);

            return;
        }
        /** @var PaymentMethodInterface|null $paymentMethod */
        $paymentMethod = $this->paymentMethodRepository->find($message->paymentMethodId());
        if (null === $paymentMethod || false === GatewayProvider::isHiPayGateway($paymentMethod)) {
            return;
        }
        $transactionReference = $this->hiPayTransactionProvider->getByPayment($payment)?->getTransactionReference();
        if (null === $transactionReference) {
            $this->logger->warning('HiPay transaction reference not found for this payment.', ['payment_id' => $payment->getId()]);

            return;
        }
        $account = $this->accountProvider->getByPaymentMethod($paymentMethod);
        $this->logger->setAccount($account);
        if (null === $account) {
            $this->logger->warning('HiPay account not found for this payment.', ['payment_id' => $payment->getId()]);

            return;
        }

        $hipayClient = $this->hiPayClientProvider->getForAccountCode($account->getCode());

        try {
            $response = $hipayClient->refundPayment($transactionReference, ($message->amount() / 100));
            $paymentRequest = $this->paymentRequestProvider->createPaymentRequest($payment, $paymentMethod, PaymentRequestInterface::ACTION_REFUND, $response);
            $this->entityManager->persist($paymentRequest);
            $this->paymentRequestProvider->setProcessState($paymentRequest);
            $details = $payment->getDetails();
            $details['refund_response'] = $response;
            $payment->setDetails($details);
            $this->entityManager->persist($payment);
            $this->paymentRequestProvider->setCompleteState($paymentRequest);
            $this->entityManager->flush();
            $this->logger->info('HiPay refunded successful.', ['payment_id' => $payment->getId(), 'transaction' => $transactionReference]);
        } catch (RuntimeException $e) {
            $this->logger->error('The refunded failed on HiPay : ' . $e->getMessage(), ['payment_id' => $payment->getId(), 'transaction' => $transactionReference]);
        }
    }
}
