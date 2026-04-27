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

namespace HiPay\SyliusHiPayPlugin\PaymentProduct;

enum CardPaymentProduct: string
{
    case CB = 'cb';
    case VISA = 'visa';
    case MASTERCARD = 'mastercard';
    case MAESTRO = 'maestro';
    case BANCONTACT = 'bcmc';
    case AMEX = 'american-express';

    public static function normalize(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return self::tryFrom($value) !== null ? PluginPaymentProduct::CARD->value : $value;
    }

    public static function getPaymentProducts(): array
    {
        return array_map(fn (self $paymentProduct) => $paymentProduct->value, self::cases());
    }
}
