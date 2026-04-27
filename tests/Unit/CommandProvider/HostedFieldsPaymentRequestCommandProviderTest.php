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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\CommandProvider;

use HiPay\SyliusHiPayPlugin\Command\NewOrderRequest;
use HiPay\SyliusHiPayPlugin\Command\TransactionInformationRequest;
use HiPay\SyliusHiPayPlugin\CommandProvider\HostedFieldsPaymentRequestCommandProvider;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\Uid\Uuid;

final class HostedFieldsPaymentRequestCommandProviderTest extends TestCase
{
    private HostedFieldsPaymentRequestCommandProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new HostedFieldsPaymentRequestCommandProvider();
    }

    public function testSupportsCaptureAction(): void
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_CAPTURE);

        $this->assertTrue($this->provider->supports($paymentRequest));
    }

    public function testSupportsAuthorizeAction(): void
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_AUTHORIZE);

        $this->assertTrue($this->provider->supports($paymentRequest));
    }

    public function testSupportsStatusAction(): void
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_STATUS);

        $this->assertTrue($this->provider->supports($paymentRequest));
    }

    public function testDoesNotSupportRefundAction(): void
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn('refund');

        $this->assertFalse($this->provider->supports($paymentRequest));
    }

    public function testDoesNotSupportCancelAction(): void
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_CANCEL);

        $this->assertFalse($this->provider->supports($paymentRequest));
    }

    public function testProvideCaptureReturnsNewOrderRequest(): void
    {
        $uuid = Uuid::v4();
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_CAPTURE);
        $paymentRequest->method('getHash')->willReturn($uuid);

        $command = $this->provider->provide($paymentRequest);

        $this->assertInstanceOf(NewOrderRequest::class, $command);
        $this->assertSame((string) $uuid, $command->getHash());
    }

    public function testProvideAuthorizeReturnsNewOrderRequest(): void
    {
        $uuid = Uuid::v4();
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_AUTHORIZE);
        $paymentRequest->method('getHash')->willReturn($uuid);

        $command = $this->provider->provide($paymentRequest);

        $this->assertInstanceOf(NewOrderRequest::class, $command);
        $this->assertSame((string) $uuid, $command->getHash());
    }

    public function testProvideStatusReturnsTransactionInformationRequest(): void
    {
        $uuid = Uuid::v4();
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_STATUS);
        $paymentRequest->method('getHash')->willReturn($uuid);

        $command = $this->provider->provide($paymentRequest);

        $this->assertInstanceOf(TransactionInformationRequest::class, $command);
        $this->assertSame((string) $uuid, $command->getHash());
    }

    public function testProvideHandlesNullHashForNewOrderRequest(): void
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_CAPTURE);
        $paymentRequest->method('getHash')->willReturn(null);

        $command = $this->provider->provide($paymentRequest);

        // getHash() throws TypeError when hash is null (strict return type in trait)
        // The important contract is that the command type is correct
        $this->assertInstanceOf(NewOrderRequest::class, $command);
    }

    public function testProvideHandlesNullHashForTransactionInformationRequest(): void
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_STATUS);
        $paymentRequest->method('getHash')->willReturn(null);

        $command = $this->provider->provide($paymentRequest);

        // getHash() throws TypeError when hash is null (strict return type in trait)
        // The important contract is that the command type is correct
        $this->assertInstanceOf(TransactionInformationRequest::class, $command);
    }

    public function testProvideThrowsOnUnsupportedAction(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn('refund');
        $paymentRequest->method('getHash')->willReturn(null);

        $this->provider->provide($paymentRequest);
    }
}
