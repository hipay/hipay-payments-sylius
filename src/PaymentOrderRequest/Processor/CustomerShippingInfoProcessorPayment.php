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

namespace HiPay\SyliusHiPayPlugin\PaymentOrderRequest\Processor;

use HiPay\Fullservice\Gateway\Request\Info\CustomerShippingInfoRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestProcessorInterface;

/**
 * Sets customerShippingInfo from the order's shipping address.
 */
final class CustomerShippingInfoProcessorPayment implements PaymentOrderRequestProcessorInterface
{
    public function process(OrderRequest $orderRequest, PaymentOrderRequestContext $context): void
    {
        $shippingAddress = $context->order->getShippingAddress();
        if (null === $shippingAddress) {
            return;
        }

        $customerShippingInfo = new CustomerShippingInfoRequest();
        $customerShippingInfo->shipto_firstname = (string) $shippingAddress->getFirstName();
        $customerShippingInfo->shipto_lastname = (string) $shippingAddress->getLastName();
        $customerShippingInfo->shipto_streetaddress = (string) $shippingAddress->getStreet();
        $customerShippingInfo->shipto_city = (string) $shippingAddress->getCity();
        $customerShippingInfo->shipto_zipcode = (string) $shippingAddress->getPostcode();
        $customerShippingInfo->shipto_country = (string) $shippingAddress->getCountryCode();
        $customerShippingInfo->shipto_phone = (string) $shippingAddress->getPhoneNumber();
        $customerShippingInfo->shipto_state = (string) $shippingAddress->getProvinceCode();

        $orderRequest->customerShippingInfo = $customerShippingInfo;
    }
}
