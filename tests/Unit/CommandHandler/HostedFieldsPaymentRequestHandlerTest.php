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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\CommandHandler;

use HiPay\SyliusHiPayPlugin\Command\HostedFieldsPaymentRequest;
use HiPay\SyliusHiPayPlugin\CommandHandler\HostedFieldsPaymentRequestHandler;
use PHPUnit\Framework\TestCase;

/**
 * The handler is a no-op: it exists solely to satisfy the Messenger bus contract.
 * All processing is done by HostedFieldsHttpResponseProvider.
 */
final class HostedFieldsPaymentRequestHandlerTest extends TestCase
{
    public function testInvokeIsCallableAndDoesNothing(): void
    {
        $handler = new HostedFieldsPaymentRequestHandler();
        $command = new HostedFieldsPaymentRequest('test-hash');

        $handler($command);

        $this->addToAssertionCount(1);
    }

    public function testHandlesNullHashCommand(): void
    {
        $handler = new HostedFieldsPaymentRequestHandler();
        $command = new HostedFieldsPaymentRequest(null);

        $handler($command);

        $this->addToAssertionCount(1);
    }
}
