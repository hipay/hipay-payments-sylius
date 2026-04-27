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

namespace HiPay\SyliusHiPayPlugin\OrderPay\Provider;

use HiPay\Fullservice\Enum\Transaction\TransactionState;
use HiPay\SyliusHiPayPlugin\OrderPay\Handler\HiPayPaymentStateFlashHandlerDecorator;
use HiPay\SyliusHiPayPlugin\Payment\OrderAdvisoryLockInterface;
use HiPay\SyliusHiPayPlugin\Payment\OrphanPaymentCancellerInterface;
use function is_string;
use Sylius\Bundle\PaymentBundle\Provider\HttpResponseProviderInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class HostedFieldsHttpResponseProvider implements HttpResponseProviderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly OrphanPaymentCancellerInterface $orphanPaymentCanceller,
        private readonly OrderAdvisoryLockInterface $orderAdvisoryLock,
    ) {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function supports(
        RequestConfiguration $requestConfiguration,
        PaymentRequestInterface $paymentRequest,
    ): bool {
        return in_array($paymentRequest->getAction(), [
            PaymentRequestInterface::ACTION_CAPTURE,
            PaymentRequestInterface::ACTION_AUTHORIZE,
            PaymentRequestInterface::ACTION_STATUS,
            PaymentRequestInterface::ACTION_CANCEL,
            'capture_request',
            'authorize_request',
        ], true);
    }

    public function getResponse(
        RequestConfiguration $requestConfiguration,
        PaymentRequestInterface $paymentRequest,
    ): Response {
        unset($requestConfiguration);

        // Signal to HiPayPaymentStateFlashHandlerDecorator that this payment
        // was handled by HiPay. The decorator will suppress the native Sylius
        // "info" flash to avoid duplicate or incorrectly styled messages on the
        // confirmation page. See HIPASYLU001-104.
        $this->requestStack->getCurrentRequest()?->attributes->set(
            HiPayPaymentStateFlashHandlerDecorator::REQUEST_ATTR_HIPAY_PAYMENT,
            true,
        );

        $data = $paymentRequest->getResponseData();
        $state = $data['state'] ?? 'error';

        $payment = $paymentRequest->getPayment();
        $order = $payment instanceof PaymentInterface ? $payment->getOrder() : null;
        $tokenValue = $order instanceof OrderInterface ? $order->getTokenValue() : null;

        // Advisory lock: serialise after-pay redirect with the webhook so
        // only one thread processes the order at a time. See HIPASYLU001-108.
        $lockName = $order instanceof OrderInterface ? $this->orderAdvisoryLock->acquire($order) : null;

        try {
            // Cleanup (layer 2): cancel orphan "new" HiPay payments that may
            // have been created by a concurrent thread before the lock.
            if ($order instanceof OrderInterface) {
                $this->orphanPaymentCanceller->cancelOrphanPayments($order);
            }

            return match ($state) {
                TransactionState::FORWARDING => $this->buildForwardingResponse($data),
                TransactionState::COMPLETED, TransactionState::PENDING => $this->buildThankYouResponse($tokenValue, $data),
                default => $this->buildOrderShowResponse($tokenValue, $data),
            };
        } finally {
            $this->orderAdvisoryLock->release($lockName);
        }
    }

    private function buildForwardingResponse(array $data): RedirectResponse
    {
        $forwardUrl = $data['forwardUrl'] ?? null;

        if (is_string($forwardUrl) && '' !== $forwardUrl) {
            return new RedirectResponse($forwardUrl);
        }

        return $this->buildThankYouResponse();
    }

    private function buildThankYouResponse(?string $tokenValue = null, ?array $data = null): RedirectResponse
    {
        $parameters = [];
        if (null !== ($data['referenceToPay'] ?? null) && '""' !== $data['referenceToPay'] && '' !== $data['referenceToPay']) {
            $parameters['referenceToPay'] = $data['referenceToPay'];
            $parameters['currency'] = $data['currency'] ?? null;
            $parameters['tokenValue'] = $tokenValue;
        }

        return new RedirectResponse(
            $this->urlGenerator->generate('sylius_shop_order_thank_you', $parameters),
        );
    }

    private function buildOrderShowResponse(?string $tokenValue, array $data): RedirectResponse
    {
        $errorMessage = $data['reason']['message'] ?? null;
        if (null !== $errorMessage) {
            // ltrim() uses a character mask, not a prefix: ltrim("Refused","ero: ")
            // would incorrectly produce "fused". Use preg_replace for safe prefix
            // removal instead.
            $sanitizedMessage = (string) preg_replace('/^error:\s*/i', '', $errorMessage);
            // @phpstan-ignore-next-line
            $this->requestStack->getSession()->getFlashBag()->add(
                'error',
                $sanitizedMessage,
            );
        }

        if (null !== $tokenValue) {
            return new RedirectResponse(
                $this->urlGenerator->generate('sylius_shop_order_show', [
                    'tokenValue' => $tokenValue,
                ]),
            );
        }

        return new RedirectResponse(
            $this->urlGenerator->generate('sylius_shop_homepage'),
        );
    }
}
