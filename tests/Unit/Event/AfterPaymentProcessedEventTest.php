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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Event;

use HiPay\SyliusHiPayPlugin\Event\AfterPaymentProcessedEvent;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class AfterPaymentProcessedEventTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $paymentRequest = $this->createMock(PaymentRequestInterface::class);
        $responseData = ['state' => 'completed', 'transaction_reference' => 'REF-001'];

        $event = new AfterPaymentProcessedEvent($payment, $paymentRequest, $responseData, 'capture');

        $this->assertSame($payment, $event->getPayment());
        $this->assertSame($paymentRequest, $event->getPaymentRequest());
        $this->assertSame($responseData, $event->getResponseData());
        $this->assertSame('capture', $event->getAction());
    }

    public function testResponseDataIsMutable(): void
    {
        $event = new AfterPaymentProcessedEvent(
            $this->createMock(PaymentInterface::class),
            $this->createMock(PaymentRequestInterface::class),
            ['state' => 'completed'],
            'capture',
        );

        $event->setResponseData(['state' => 'completed', 'custom' => 'value']);

        $this->assertSame('value', $event->getResponseData()['custom']);
    }

    public function testHasNoCustomResponseByDefault(): void
    {
        $event = new AfterPaymentProcessedEvent(
            $this->createMock(PaymentInterface::class),
            $this->createMock(PaymentRequestInterface::class),
            ['state' => 'completed'],
            'capture',
        );

        $this->assertFalse($event->hasCustomResponse());
        $this->assertNull($event->getResponse());
    }

    public function testSetResponseOverridesDefault(): void
    {
        $event = new AfterPaymentProcessedEvent(
            $this->createMock(PaymentInterface::class),
            $this->createMock(PaymentRequestInterface::class),
            ['state' => 'completed'],
            'capture',
        );

        $customResponse = new RedirectResponse('/custom-page');
        $event->setResponse($customResponse);

        $this->assertTrue($event->hasCustomResponse());
        $this->assertSame($customResponse, $event->getResponse());
    }
}
