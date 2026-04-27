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

// HiPay status 113 = Refused → TRANSITION_FAIL
return new RemoteEvent(
    name: 'hipay.notification',
    id: '1',
    payload: [
        'state' => 'declined',
        'test' => 'true',
        'mid' => '00001342917',
        'attempt_id' => '1',
        'authorization_code' => '',
        'transaction_reference' => '800417743755',
        'acquirer_transaction_reference' => '',
        'date_created' => '2026-03-05T12:06:55+0000',
        'date_updated' => '2026-03-05T12:06:56+0000',
        'date_authorized' => '',
        'status' => '113',
        'message' => 'Refused',
        'authorized_amount' => '0.00',
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
