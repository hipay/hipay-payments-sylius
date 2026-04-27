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

namespace HiPay\SyliusHiPayPlugin\Command;

use Sylius\Bundle\PaymentBundle\Command\PaymentRequestHashAwareInterface;
use Sylius\Bundle\PaymentBundle\Command\PaymentRequestHashAwareTrait;

/**
 * Unified command for the Hosted Fields (API-only) payment flow.
 * Used for ACTION_CAPTURE, ACTION_AUTHORIZE, and ACTION_STATUS.
 * All processing logic is handled synchronously by the HttpResponseProvider,
 * not the Messenger handler (which is a no-op satisfying the bus contract).
 */
final class HostedFieldsPaymentRequest implements PaymentRequestHashAwareInterface
{
    use PaymentRequestHashAwareTrait;

    public function __construct(protected ?string $hash)
    {
    }
}
