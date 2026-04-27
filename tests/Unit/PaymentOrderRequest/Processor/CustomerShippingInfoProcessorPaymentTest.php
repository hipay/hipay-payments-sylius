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
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\CustomerShippingInfoProcessorPayment;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final class CustomerShippingInfoProcessorPaymentTest extends TestCase
{
    private CustomerShippingInfoProcessorPayment $processor;

    protected function setUp(): void
    {
        $this->processor = new CustomerShippingInfoProcessorPayment();
    }

    public function testProcessSetsShippingInfoFromOrderAddress(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getFirstName')->willReturn('Marie');
        $address->method('getLastName')->willReturn('Martin');
        $address->method('getStreet')->willReturn('12 avenue des Champs');
        $address->method('getCity')->willReturn('Lyon');
        $address->method('getPostcode')->willReturn('69001');
        $address->method('getCountryCode')->willReturn('FR');
        $address->method('getPhoneNumber')->willReturn('+33478901234');
        $address->method('getProvinceCode')->willReturn('FR-69');

        $context = $this->createContext($address);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertNotNull($orderRequest->customerShippingInfo);
        $this->assertSame('Marie', $orderRequest->customerShippingInfo->shipto_firstname);
        $this->assertSame('Martin', $orderRequest->customerShippingInfo->shipto_lastname);
        $this->assertSame('12 avenue des Champs', $orderRequest->customerShippingInfo->shipto_streetaddress);
        $this->assertSame('Lyon', $orderRequest->customerShippingInfo->shipto_city);
        $this->assertSame('69001', $orderRequest->customerShippingInfo->shipto_zipcode);
        $this->assertSame('FR', $orderRequest->customerShippingInfo->shipto_country);
        $this->assertSame('+33478901234', $orderRequest->customerShippingInfo->shipto_phone);
        $this->assertSame('FR-69', $orderRequest->customerShippingInfo->shipto_state);
    }

    public function testProcessSkipsShippingInfoWhenNoShippingAddress(): void
    {
        $context = $this->createContext(null);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertNull($orderRequest->customerShippingInfo);
    }

    public function testProcessHandlesMissingPhoneAndProvince(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getFirstName')->willReturn('Sam');
        $address->method('getLastName')->willReturn('Taylor');
        $address->method('getStreet')->willReturn('1 High Street');
        $address->method('getCity')->willReturn('Manchester');
        $address->method('getPostcode')->willReturn('M1 1AA');
        $address->method('getCountryCode')->willReturn('GB');
        $address->method('getPhoneNumber')->willReturn(null);
        $address->method('getProvinceCode')->willReturn(null);

        $context = $this->createContext($address);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertNotNull($orderRequest->customerShippingInfo);
        $this->assertSame('', $orderRequest->customerShippingInfo->shipto_phone);
        $this->assertSame('', $orderRequest->customerShippingInfo->shipto_state);
    }

    private function createContext(?AddressInterface $shippingAddress): PaymentOrderRequestContext
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getShippingAddress')->willReturn($shippingAddress);

        $payment = $this->createMock(PaymentInterface::class);

        return new PaymentOrderRequestContext(
            order: $order,
            payment: $payment,
            paymentRequest: $this->createMock(PaymentRequestInterface::class),
            account: $this->createMock(AccountInterface::class),
            paymentProduct: 'card',
            payload: [],
            gatewayConfig: [],
            action: PaymentRequestInterface::ACTION_CAPTURE,
        );
    }
}
