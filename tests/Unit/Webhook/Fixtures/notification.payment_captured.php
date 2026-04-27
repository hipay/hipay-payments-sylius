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

// Real HiPay notification structure – status 118 = Captured (from live test).
return new RemoteEvent(
    name: 'hipay.notification',
    id: '1',
    payload: [
        'state' => 'completed',
        'test' => 'true',
        'mid' => '00001342917',
        'attempt_id' => '1',
        'authorization_code' => '310214474',
        'transaction_reference' => '800417743755',
        'acquirer_transaction_reference' => '4463926236',
        'date_created' => '2026-03-05T12:06:55+0000',
        'date_updated' => '2026-03-05T12:07:06+0000',
        'date_authorized' => '2026-03-05T12:07:03+0000',
        'status' => '118',
        'message' => 'Captured',
        'authorized_amount' => '15.00',
        'captured_amount' => '15.00',
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
        'payment_method' => [
            'token' => '74a2150be6c45232d49b1d3f218fbfd6ae2e166eeccb44f4d4553f7ad814739b',
            'card_id' => '9fd81707-8f41-4a01-b6ed-279954336ada',
            'brand' => 'VISA',
            'pan' => '411111******1111',
            'card_holder' => 'TEST',
            'card_expiry_month' => '12',
            'card_expiry_year' => '2026',
            'issuer' => 'CONOTOXIA SP. Z O.O',
            'country' => 'PL',
        ],
        'order' => [
            'id' => 'TEST-NOTIF-001',
            'date_created' => '2026-03-05T12:03:32+0000',
            'attempts' => '1',
            'amount' => '15.00',
            'shipping' => '0.00',
            'tax' => '0.00',
            'decimals' => '2',
            'currency' => 'EUR',
            'customer_id' => '',
            'language' => 'en_US',
            'email' => '',
        ],
    ],
);
