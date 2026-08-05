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

use HiPay\SyliusHiPayPlugin\Command\HostedPagePaymentRequest;
use HiPay\SyliusHiPayPlugin\Command\TransactionInformationRequest;
use HiPay\SyliusHiPayPlugin\CommandProvider\HostedPagePaymentRequestCommandProvider;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\Uid\Uuid;

final class HostedPagePaymentRequestCommandProviderTest extends TestCase
{
    private HostedPagePaymentRequestCommandProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new HostedPagePaymentRequestCommandProvider();
    }

    public function testSupportsCaptureAction(): void
    {
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_CAPTURE);

        $this->assertTrue($this->provider->supports($paymentRequest));
    }

    public function testProvideCaptureReturnsHostedPagePaymentRequest(): void
    {
        $uuid = Uuid::v4();
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn(PaymentRequestInterface::ACTION_CAPTURE);
        $paymentRequest->method('getHash')->willReturn($uuid);

        $command = $this->provider->provide($paymentRequest);

        $this->assertInstanceOf(HostedPagePaymentRequest::class, $command);
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

    public function testProvideThrowsOnUnsupportedAction(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $paymentRequest->method('getAction')->willReturn('refund');

        $this->provider->provide($paymentRequest);
    }
}
