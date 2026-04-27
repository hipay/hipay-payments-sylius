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

namespace HiPay\SyliusHiPayPlugin\PaymentOrderRequest;

use InvalidArgumentException;
use function sprintf;

final class PaymentOrderRequestBuilderRegistry implements PaymentOrderRequestBuilderRegistryInterface
{
    /** @var array<string, PaymentOrderRequestBuilderInterface> */
    private array $builders = [];

    /**
     * @param iterable<string, PaymentOrderRequestBuilderInterface> $builders Tagged iterator indexed by code
     */
    public function __construct(iterable $builders)
    {
        foreach ($builders as $code => $builder) {
            $this->builders[$code] = $builder;
        }
    }

    public function get(string $paymentProduct): PaymentOrderRequestBuilderInterface
    {
        foreach ($this->builders as $builder) {
            if ($builder->supports($paymentProduct)) {
                return $builder;
            }
        }

        throw new InvalidArgumentException(sprintf(
            'No PaymentOrderRequest found for payment product "%s". Available: %s',
            $paymentProduct,
            implode(', ', array_keys($this->builders)),
        ));
    }

    public function has(string $paymentProduct): bool
    {
        foreach ($this->builders as $builder) {
            if ($builder->supports($paymentProduct)) {
                return true;
            }
        }

        return false;
    }
}
