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

namespace HiPay\SyliusHiPayPlugin\Security;

use HiPay\SyliusHiPayPlugin\Exception\InvalidPaymentPayloadException;
use Sylius\Component\Customer\Model\CustomerInterface;

interface CardTokenOwnershipGuardInterface
{
    /**
     * @throws InvalidPaymentPayloadException when the token belongs to another customer
     */
    public function assertTokenBelongsToCustomer(string $token, ?CustomerInterface $customer): void;
}
