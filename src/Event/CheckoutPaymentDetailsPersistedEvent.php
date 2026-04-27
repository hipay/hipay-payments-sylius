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

namespace HiPay\SyliusHiPayPlugin\Event;

use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after payment details were flushed in the checkout LiveComponent.
 */
final class CheckoutPaymentDetailsPersistedEvent extends Event
{
    public function __construct(
        private readonly PaymentInterface $payment,
    ) {
    }

    public function getPayment(): PaymentInterface
    {
        return $this->payment;
    }
}
