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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Payment\Capture;

use HiPay\SyliusHiPayPlugin\Payment\Capture\PaymentCaptureEligibilityChecker;
use HiPay\SyliusHiPayPlugin\Payment\HiPayStatus;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class PaymentCaptureEligibilityCheckerTest extends TestCase
{
    private RepositoryInterface $paymentRequestRepository;

    private PaymentCaptureEligibilityChecker $checker;

    protected function setUp(): void
    {
        $this->paymentRequestRepository = $this->createMock(RepositoryInterface::class);
        $this->checker = new PaymentCaptureEligibilityChecker($this->paymentRequestRepository);
    }

    public function testNotEligibleWhenPaymentMethodIsNotHiPay(): void
    {
        $payment = $this->createPayment('stripe', PaymentInterface::STATE_AUTHORIZED);

        $this->assertFalse($this->checker->isEligible($payment));
    }

    public function testNotEligibleWhenStateIsNotAuthorized(): void
    {
        $payment = $this->createPayment('hipay_hosted_fields', PaymentInterface::STATE_COMPLETED);

        $this->assertFalse($this->checker->isEligible($payment));
    }

    public function testNotEligibleWhenLatestHiPayStatusIsNotAuthorized(): void
    {
        $payment = $this->createPayment('hipay_hosted_fields', PaymentInterface::STATE_AUTHORIZED);
        $this->stubPaymentRequests(
            latestStatus: HiPayStatus::CaptureRequested->value,
            captureRequests: [],
        );

        $this->assertFalse($this->checker->isEligible($payment));
    }

    public function testNotEligibleWhenACaptureRequestAlreadyExists(): void
    {
        $payment = $this->createPayment('hipay_hosted_fields', PaymentInterface::STATE_AUTHORIZED);
        $this->stubPaymentRequests(
            latestStatus: HiPayStatus::Authorized->value,
            captureRequests: [$this->createPaymentRequest(PaymentRequestInterface::STATE_PROCESSING)],
        );

        $this->assertFalse($this->checker->isEligible($payment));
    }

    public function testEligibleWhenAuthorizedAndNoCaptureRequested(): void
    {
        $payment = $this->createPayment('hipay_hosted_page', PaymentInterface::STATE_AUTHORIZED);
        $this->stubPaymentRequests(
            latestStatus: HiPayStatus::Authorized->value,
            captureRequests: [],
        );

        $this->assertTrue($this->checker->isEligible($payment));
    }

    public function testEligibleWhenOnlyFailedCaptureRequestExists(): void
    {
        $payment = $this->createPayment('hipay_hosted_fields', PaymentInterface::STATE_AUTHORIZED);
        $this->stubPaymentRequests(
            latestStatus: HiPayStatus::Authorized->value,
            captureRequests: [$this->createPaymentRequest(PaymentRequestInterface::STATE_FAILED)],
        );

        $this->assertTrue($this->checker->isEligible($payment));
    }

    private function createPayment(string $factoryName, string $state): PaymentInterface
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn($factoryName);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getState')->willReturn($state);

        return $payment;
    }

    /**
     * @param PaymentRequestInterface[] $captureRequests
     */
    private function stubPaymentRequests(int $latestStatus, array $captureRequests): void
    {
        $statusRequest = $this->createMock(PaymentRequestInterface::class);
        $statusRequest->method('getPayload')->willReturn(['status' => $latestStatus]);

        $this->paymentRequestRepository
            ->method('findBy')
            ->willReturnCallback(static function (array $criteria) use ($statusRequest, $captureRequests): array {
                if (array_key_exists('action', $criteria)) {
                    return $captureRequests;
                }

                return [$statusRequest];
            });
    }

    private function createPaymentRequest(string $state): PaymentRequestInterface
    {
        $request = $this->createMock(PaymentRequestInterface::class);
        $request->method('getState')->willReturn($state);

        return $request;
    }
}
