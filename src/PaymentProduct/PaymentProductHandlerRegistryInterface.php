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

namespace HiPay\SyliusHiPayPlugin\PaymentProduct;

use Sylius\Component\Core\Model\PaymentMethodInterface;

interface PaymentProductHandlerRegistryInterface
{
    public function get(string $code): PaymentProductHandlerInterface;

    public function getForPaymentProduct(string $paymentProduct): ?PaymentProductHandlerInterface;

    public function getForPaymentMethod(PaymentMethodInterface $paymentMethod): ?PaymentProductHandlerInterface;

    /**
     * @return PaymentProductHandlerInterface[]
     */
    public function getAll(): array;
}
