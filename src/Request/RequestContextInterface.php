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

namespace HiPay\SyliusHiPayPlugin\Request;

use Sylius\Component\Core\Model\OrderInterface;

/**
 * Shared contract for request-building contexts (order request and capture/maintenance
 * request), so processors that only depend on the order can serve both pipelines.
 */
interface RequestContextInterface
{
    public function getOrder(): OrderInterface;
}
