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

namespace HiPay\SyliusHiPayPlugin\Payment;

enum PaymentRequestAction: string
{
    case AUTHORIZE_REQUEST = 'authorize_request';
    case CAPTURE_REQUEST = 'capture_request';
}
