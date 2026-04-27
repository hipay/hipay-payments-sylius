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

use HiPay\Fullservice\Gateway\Request\Info\CustomerBillingInfoRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestContext;
use HiPay\SyliusHiPayPlugin\PaymentOrderRequest\PaymentOrderRequestProcessorInterface;

/**
 * Sets customerBillingInfo from the order's billing address.
 */
final class CustomerBillingInfoProcessorPayment implements PaymentOrderRequestProcessorInterface
{
    public function process(OrderRequest $orderRequest, PaymentOrderRequestContext $context): void
    {
        $billingAddress = $context->order->getBillingAddress();
        if (null === $billingAddress) {
            return;
        }

        $customerBillingInfo = new CustomerBillingInfoRequest();
        $customerBillingInfo->firstname = (string) $billingAddress->getFirstName();
        $customerBillingInfo->lastname = (string) $billingAddress->getLastName();
        $customerBillingInfo->email = (string) $context->order->getCustomer()?->getEmail();
        $customerBillingInfo->streetaddress = (string) $billingAddress->getStreet();
        $customerBillingInfo->city = (string) $billingAddress->getCity();
        $customerBillingInfo->zipcode = (string) $billingAddress->getPostcode();
        $customerBillingInfo->country = (string) $billingAddress->getCountryCode();
        $customerBillingInfo->phone = (string) $billingAddress->getPhoneNumber();
        $customerBillingInfo->state = (string) $billingAddress->getProvinceCode();

        $customer = $context->order->getCustomer();
        if (null !== $customer) {
            $gender = $customer->getGender();
            $customerBillingInfo->gender = match ($gender) {
                'm' => 'M',
                'f' => 'F',
                default => 'U',
            };

            $birthday = $customer->getBirthday();
            if (null !== $birthday) {
                $customerBillingInfo->birthdate = $birthday->format('Ymd');
            }
        }

        $orderRequest->customerBillingInfo = $customerBillingInfo;
    }
}
