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

namespace HiPay\SyliusHiPayPlugin\CommandHandler;

use HiPay\SyliusHiPayPlugin\Command\HostedFieldsPaymentRequest;

/**
 * No-op handler satisfying the Symfony Messenger contract.
 *
 * All payment processing (HiPay API call, state transitions, redirect logic)
 * is handled synchronously by HostedFieldsHttpResponseProvider. This handler
 * exists solely because every dispatched message MUST have a registered handler
 * on the sylius.payment_request.command_bus — regardless of whether the bus
 * transport is sync:// or doctrine://.
 *
 * Registered via services.php tag: messenger.message_handler with bus=sylius.payment_request.command_bus.
 */
final readonly class HostedFieldsPaymentRequestHandler
{
    public function __invoke(HostedFieldsPaymentRequest $command): void
    {
        unset($command);
    }
}
