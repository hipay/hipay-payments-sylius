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

use Symfony\Component\RemoteEvent\RemoteEvent;

// HiPay status 115 = Cancelled → TRANSITION_VOID
return new RemoteEvent(
    name: 'hipay.notification',
    id: '1',
    payload: [
        'state' => 'cancelled',
        'test' => 'true',
        'mid' => '00001342917',
        'attempt_id' => '1',
        'authorization_code' => '310214474',
        'transaction_reference' => '800417743755',
        'acquirer_transaction_reference' => '4463926236',
        'date_created' => '2026-03-05T12:06:55+0000',
        'date_updated' => '2026-03-05T12:07:00+0000',
        'date_authorized' => '2026-03-05T12:06:58+0000',
        'status' => '115',
        'message' => 'Cancelled',
        'authorized_amount' => '15.00',
        'captured_amount' => '0.00',
        'refunded_amount' => '0.00',
        'decimals' => '2',
        'currency' => 'EUR',
        'ip_address' => '80.11.68.225',
        'ip_country' => 'FR',
        'device_id' => '',
        'cdata1' => '',
        'cdata2' => '',
        'cdata3' => '',
        'cdata4' => '',
        'cdata5' => '',
        'cdata6' => '',
        'cdata7' => '',
        'cdata8' => '',
        'cdata9' => '',
        'cdata10' => '',
        'avs_result' => '',
        'cvc_result' => '',
        'eci' => '7',
        'payment_product' => 'visa',
        'payment_method' => ['brand' => 'VISA'],
        'order' => ['id' => 'TEST-NOTIF-001', 'amount' => '15.00', 'currency' => 'EUR'],
    ],
);
