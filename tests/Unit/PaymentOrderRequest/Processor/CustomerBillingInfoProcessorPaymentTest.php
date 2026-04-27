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
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor\CustomerBillingInfoProcessorPayment;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;

final class CustomerBillingInfoProcessorPaymentTest extends TestCase
{
    private CustomerBillingInfoProcessorPayment $processor;

    protected function setUp(): void
    {
        $this->processor = new CustomerBillingInfoProcessorPayment();
    }

    public function testProcessSetsBillingInfoFromOrderAddress(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getFirstName')->willReturn('Jean');
        $address->method('getLastName')->willReturn('Dupont');
        $address->method('getStreet')->willReturn('1 rue de Paris');
        $address->method('getCity')->willReturn('Paris');
        $address->method('getPostcode')->willReturn('75001');
        $address->method('getCountryCode')->willReturn('FR');
        $address->method('getPhoneNumber')->willReturn('+33612345678');

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getEmail')->willReturn('jean@example.com');

        $context = $this->createContext($address, $customer);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertNotNull($orderRequest->customerBillingInfo);
        $this->assertSame('Jean', $orderRequest->customerBillingInfo->firstname);
        $this->assertSame('Dupont', $orderRequest->customerBillingInfo->lastname);
        $this->assertSame('jean@example.com', $orderRequest->customerBillingInfo->email);
        $this->assertSame('1 rue de Paris', $orderRequest->customerBillingInfo->streetaddress);
        $this->assertSame('Paris', $orderRequest->customerBillingInfo->city);
        $this->assertSame('75001', $orderRequest->customerBillingInfo->zipcode);
        $this->assertSame('FR', $orderRequest->customerBillingInfo->country);
        $this->assertSame('+33612345678', $orderRequest->customerBillingInfo->phone);
    }

    public function testProcessSkipsBillingInfoWhenNoBillingAddress(): void
    {
        $context = $this->createContext(null, null);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertNull($orderRequest->customerBillingInfo);
    }

    public function testProcessHandlesMissingCustomerEmail(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getFirstName')->willReturn('Alice');
        $address->method('getLastName')->willReturn('Smith');
        $address->method('getStreet')->willReturn('Main St');
        $address->method('getCity')->willReturn('London');
        $address->method('getPostcode')->willReturn('SW1A');
        $address->method('getCountryCode')->willReturn('GB');
        $address->method('getPhoneNumber')->willReturn(null);

        $context = $this->createContext($address, null);
        $orderRequest = new OrderRequest();

        $this->processor->process($orderRequest, $context);

        $this->assertNotNull($orderRequest->customerBillingInfo);
        $this->assertSame('', $orderRequest->customerBillingInfo->email);
        $this->assertSame('', $orderRequest->customerBillingInfo->phone);
    }

    private function createContext(
        ?AddressInterface $billingAddress,
        ?CustomerInterface $customer,
    ): PaymentOrderRequestContext {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getCustomer')->willReturn($customer);

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
