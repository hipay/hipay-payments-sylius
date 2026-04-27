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

// HiPay status 124 = Refund Requested → TRANSITION_REFUND
return new RemoteEvent(
    name: 'hipay.notification',
    id: '1',
    payload: [
        'state' => 'completed',
        'test' => 'true',
        'mid' => '00001342917',
        'attempt_id' => '1',
        'transaction_reference' => '800417743755',
        'status' => '124',
        'message' => 'Refund Requested',
        'authorized_amount' => '15.00',
        'captured_amount' => '15.00',
        'refunded_amount' => '0.00',
        'decimals' => '2',
        'currency' => 'EUR',
        'payment_method' => ['brand' => 'VISA'],
        'order' => ['id' => 'TEST-NOTIF-001', 'amount' => '15.00', 'currency' => 'EUR'],
    ],
);
