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

namespace HiPay\SyliusHiPayPlugin\Processor;

use Sylius\Component\Core\Model\PaymentInterface;

interface ManualCaptureProcessorInterface
{
    /**
     * Process manual capture of an authorized payment.
     *
     * @throws \HiPay\SyliusHiPayPlugin\Exception\PaymentActionException If capture cannot be performed
     */
    public function process(PaymentInterface $payment): void;
}
