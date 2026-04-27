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

namespace HiPay\SyliusHiPayPlugin\Validator;

use Symfony\Component\HttpFoundation\Request;

interface HmacValidatorInterface
{
    /**
     * Validates if the HiPay webhook signature in the Request matches the calculated HMAC.
     */
    public function validate(Request $request): bool;
}
