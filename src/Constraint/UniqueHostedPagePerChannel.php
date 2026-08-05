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

namespace HiPay\SyliusHiPayPlugin\Constraint;

use Attribute;
use HiPay\SyliusHiPayPlugin\Validator\UniqueHostedPagePerChannelValidator;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_CLASS)]
class UniqueHostedPagePerChannel extends Constraint
{
    public string $message = 'sylius_hipay_plugin.payment_method.hosted_page.unique_per_channel';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return UniqueHostedPagePerChannelValidator::class;
    }
}
