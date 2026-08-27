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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\PaymentOrderRequest\Processor;

use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Exception\InvalidPaymentPayloadException;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\CardPaymentMethodProcessorPayment;
use HiPay\SyliusHiPayPlugin\Security\CardTokenOwnershipGuardInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Customer\Model\CustomerInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

/**
 * Security regression for V2 at the processor level: the processor must hand the browser
 * token to the ownership guard — against the order's own customer — *before* assigning it,
 * and must let a rejection abort the request. The ownership decision itself lives in
 * {@see \HiPay\SyliusHiPayPlugin\Security\CardTokenOwnershipGuard} and is covered by
 * CardTokenOwnershipGuardTest.
 */
final class CardPaymentMethodProcessorPaymentSecurityTest extends TestCase
{
    public function testItValidatesTheTokenAgainstTheOrderCustomerBeforeCharging(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCustomer')->willReturn($customer);

        $guard = $this->createMock(CardTokenOwnershipGuardInterface::class);
        $guard->expects($this->once())
            ->method('assertTokenBelongsToCustomer')
            ->with('tok_from_browser', $customer);

        $orderRequest = new OrderRequest();
        (new CardPaymentMethodProcessorPayment($guard))->process(
            $orderRequest,
            $this->context($order, ['payment_product' => 'visa', 'token' => 'tok_from_browser']),
        );

        $this->assertSame('tok_from_browser', $orderRequest->paymentMethod->cardtoken);
    }

    public function testItPropagatesTheGuardRejectionAndNeverAssignsTheToken(): void
    {
        $guard = $this->createMock(CardTokenOwnershipGuardInterface::class);
        $guard->method('assertTokenBelongsToCustomer')
            ->willThrowException(new InvalidPaymentPayloadException('Card token does not belong to this customer'));

        $orderRequest = new OrderRequest();
        $processor = new CardPaymentMethodProcessorPayment($guard);

        try {
            $processor->process(
                $orderRequest,
                $this->context($this->createMock(OrderInterface::class), ['token' => 'tok_victim']),
            );
            $this->fail('Expected InvalidPaymentPayloadException was not thrown');
        } catch (InvalidPaymentPayloadException) {
            // The rejected token must never have reached the order request.
            $this->assertFalse(isset($orderRequest->paymentMethod));
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function context(OrderInterface $order, array $payload): PaymentOrderRequestContext
    {
        return new PaymentOrderRequestContext(
            order: $order,
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'card',
            payload: $payload,
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );
    }
}
