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

namespace HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator;

use Sylius\Component\Core\Model\OrderInterface;

final class OneyShippingPhoneValidator extends OneyPhoneValidator
{
    protected string $message = 'sylius_hipay_plugin.checkout.oney.shipping_phone_invalid';

    public function getCountryCode(OrderInterface $order): ?string
    {
        return $order->getShippingAddress()?->getCountryCode();
    }

    public function getPhoneNumber(OrderInterface $order): ?string
    {
        return $order->getShippingAddress()?->getPhoneNumber();
    }
}
