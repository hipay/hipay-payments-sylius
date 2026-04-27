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

namespace HiPay\SyliusHiPayPlugin\Logging;

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use Psr\Log\LoggerInterface;

interface HiPayLoggerInterface extends LoggerInterface
{
    public function setAccount(?AccountInterface $account): void;
}
