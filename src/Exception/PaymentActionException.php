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

namespace HiPay\SyliusHiPayPlugin\Exception;

use Exception;

final class PaymentActionException extends Exception
{
    public static function create(string $message): self
    {
        return new self($message);
    }
}
