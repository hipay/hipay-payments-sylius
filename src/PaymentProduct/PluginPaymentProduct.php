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

enum PluginPaymentProduct: string
{
    case CARD = 'card';
    case ONEY_3X = '3xcb';
    case ONEY_3X_NO_FEES = '3xcb-no-fees';
    case ONEY_4X = '4xcb';
    case ONEY_4X_NO_FEES = '4xcb-no-fees';
    case ONEY_CREDIT_LONG = 'credit-long';
    case PAYPAL = 'paypal';
    case APPLE_PAY = 'apple-pay';
    case MBWAY = 'mbway';
    case MULTIBANCO = 'multibanco';
    case IDEAL = 'ideal';
    case BANCONTACT = 'bancontact';
    case ALMA_3X = 'alma-3x';
    case ALMA_4X = 'alma-4x';
    case BANCOMAT_PAY = 'bancomatpay';
    case BIZUM = 'bizum';
    case PRZELEWY_24 = 'przelewy24';
}
