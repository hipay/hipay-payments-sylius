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

namespace HiPay\SyliusHiPayPlugin\Mailer;

interface Emails
{
    public const FRAUD_SUSPICION = 'hipay_fraud_suspicion';

    public const MULTIBANCO_REFERENCE = 'hipay_multibanco_reference';
}
