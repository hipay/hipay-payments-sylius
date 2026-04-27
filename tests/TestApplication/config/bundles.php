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

use HiPay\SyliusHiPayPlugin\SyliusHiPayPlugin;
use Knp\Bundle\GaufretteBundle\KnpGaufretteBundle;
use Knp\Bundle\SnappyBundle\KnpSnappyBundle;
use Sylius\RefundPlugin\SyliusRefundPlugin;

return [
    SyliusHiPayPlugin::class => ['all' => true],
    /*
     * Syluis refund plugin
     */
    KnpSnappyBundle::class => ['all' => true],
    KnpGaufretteBundle::class => ['all' => true],
    SyliusRefundPlugin::class => ['all' => true],
];
