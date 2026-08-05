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

use HiPay\Fullservice\Enum\Transaction\TransactionState;
use HiPay\Fullservice\Gateway\Model\HostedPaymentPage;
use HiPay\Fullservice\Gateway\Model\Order;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\HostedPaymentPageResponseMapper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class HostedPaymentPageResponseMapperTest extends TestCase
{
    private HostedPaymentPageResponseMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new HostedPaymentPageResponseMapper();
    }

    public function testMapReturnsForwardingStateWithForwardUrl(): void
    {
        $hostedPaymentPage = new HostedPaymentPage(
            '00001342917',
            'https://secure-gateway.hipay-tpp.com/payment/forward/abc123',
            null,
        );

        $response = $this->mapper->map($hostedPaymentPage);

        $this->assertSame(TransactionState::FORWARDING, $response['state']);
        $this->assertSame('https://secure-gateway.hipay-tpp.com/payment/forward/abc123', $response['forwardUrl']);
        $this->assertSame('00001342917', $response['mid']);
    }

    public function testMapThrowsWhenForwardUrlIsEmpty(): void
    {
        $hostedPaymentPage = new HostedPaymentPage('00001342917', '', $this->createMock(Order::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('forward URL');

        $this->mapper->map($hostedPaymentPage);
    }
}
