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
 * Dispatched after decoding the browser JSON payload and before persisting payment details.
 */
final class CheckoutPaymentDetailsDecodedEvent extends Event
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        private array $details,
        private readonly PaymentInterface $payment,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * @param array<string, mixed> $details
     */
    public function setDetails(array $details): void
    {
        $this->details = $details;
    }

    public function getPayment(): PaymentInterface
    {
        return $this->payment;
    }
}
