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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\PaymentOrderRequest;

use Error;
use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final class PaymentOrderRequestContextTest extends TestCase
{
    public function testConstructorExposesAllPropertiesReadonly(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $account = $this->createMock(AccountInterface::class);

        $context = new PaymentOrderRequestContext(
            order: $order,
            payment: $payment,
            paymentRequest: $paymentRequest,
            account: $account,
            paymentProduct: 'visa',
            payload: ['token' => 'tok_123', 'payment_product' => 'visa'],
            gatewayConfig: ['account' => 'main', 'three_ds_mode' => '3ds_mandatory'],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );

        $this->assertSame($order, $context->order);
        $this->assertSame($payment, $context->payment);
        $this->assertSame($paymentRequest, $context->paymentRequest);
        $this->assertSame($account, $context->account);
        $this->assertSame('visa', $context->paymentProduct);
        $this->assertSame(['token' => 'tok_123', 'payment_product' => 'visa'], $context->payload);
        $this->assertSame(['account' => 'main', 'three_ds_mode' => '3ds_mandatory'], $context->gatewayConfig);
        $this->assertSame(PaymentRequestInterface::ACTION_CAPTURE, $context->action);
    }

    public function testContextAllowsEmptyPayloadAndGatewayConfig(): void
    {
        $context = new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'cb',
            payload: [],
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_AUTHORIZE,
        );

        $this->assertSame([], $context->payload);
        $this->assertSame([], $context->gatewayConfig);
        $this->assertSame('cb', $context->paymentProduct);
    }

    public function testContextIsReadonly(): void
    {
        $context = new PaymentOrderRequestContext(
            order: $this->createMock(OrderInterface::class),
            payment: $this->createMock(PaymentInterface::class),
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'card',
            payload: [],
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );

        $this->expectException(Error::class);

        /** @phpstan-ignore-next-line */
        $context->paymentProduct = 'paypal';
    }
}
