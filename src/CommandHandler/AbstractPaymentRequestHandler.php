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

namespace HiPay\SyliusHiPayPlugin\CommandHandler;

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Client\ClientProviderInterface;
use HiPay\SyliusHiPayPlugin\Entity\TransactionInterface;
use HiPay\SyliusHiPayPlugin\Event\AfterPaymentProcessedEvent;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLoggerInterface;
use HiPay\SyliusHiPayPlugin\Mailer\FraudSuspicionEmailManagerInterface;
use HiPay\SyliusHiPayPlugin\Payment\PaymentTransitions as HiPayPaymentTransitions;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestBuilderRegistryInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContextFactoryInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PaymentBundle\Provider\PaymentRequestProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

abstract readonly class AbstractPaymentRequestHandler
{
    /**
     * @param FactoryInterface<TransactionInterface> $transactionFactory
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        protected PaymentRequestProviderInterface $paymentRequestProvider,
        protected StateMachineInterface $stateMachine,
        protected ClientProviderInterface $hiPayClientProvider,
        protected PaymentOrderRequestBuilderRegistryInterface $builderRegistry,
        protected NormalizerInterface $serializer,
        protected EntityManagerInterface $entityManager,
        protected FactoryInterface $transactionFactory,
        protected PaymentOrderRequestContextFactoryInterface $paymentOrderRequestContextFactory,
        protected RepositoryInterface $transationRepository,
        protected HiPayLoggerInterface $logger,
        protected FraudSuspicionEmailManagerInterface $fraudSuspicionEmailManager,
        protected EventDispatcherInterface $eventDispatcher,
    ) {
    }

    protected function setAllProcessState(PaymentRequestInterface $paymentRequest, PaymentInterface $payment): void
    {
        if ($this->stateMachine->can($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS)) {
            $this->stateMachine->apply(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_PROCESS,
            );
        }

        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_PROCESS)) {
            $this->stateMachine->apply(
                $payment,
                PaymentTransitions::GRAPH,
                PaymentTransitions::TRANSITION_PROCESS,
            );
        }
    }

    protected function setAllFailState(PaymentRequestInterface $paymentRequest, PaymentInterface $payment): void
    {
        if ($this->stateMachine->can($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_FAIL)) {
            $this->stateMachine->apply(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_FAIL,
            );
        }

        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL)) {
            $this->stateMachine->apply(
                $payment,
                PaymentTransitions::GRAPH,
                PaymentTransitions::TRANSITION_FAIL,
            );
        }
    }

    protected function setCompleteState(PaymentRequestInterface $paymentRequest): void
    {
        if ($this->stateMachine->can($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE)) {
            $this->stateMachine->apply(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_COMPLETE,
            );
        }
    }

    protected function setOnHold(PaymentRequestInterface $paymentRequest, PaymentInterface $payment): void
    {
        if ($this->stateMachine->can($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_COMPLETE)) {
            $this->stateMachine->apply(
                $paymentRequest,
                PaymentRequestTransitions::GRAPH,
                PaymentRequestTransitions::TRANSITION_COMPLETE,
            );
        }

        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, HiPayPaymentTransitions::HOLD)) {
            $this->stateMachine->apply(
                $payment,
                PaymentTransitions::GRAPH,
                HiPayPaymentTransitions::HOLD,
            );
        }
    }

    /**
     * @param array<string, mixed> $responseData
     */
    protected function dispatchAfterPaymentProcessedEvent(
        PaymentInterface $payment,
        PaymentRequestInterface $paymentRequest,
        array $responseData,
        string $action,
    ): void {
        $afterEvent = new AfterPaymentProcessedEvent($payment, $paymentRequest, $responseData, $action);
        $this->eventDispatcher->dispatch($afterEvent);
        $paymentRequest->setResponseData($afterEvent->getResponseData());
    }
}
