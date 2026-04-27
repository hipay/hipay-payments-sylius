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

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

class PaymentProductHandlerRegistry implements PaymentProductHandlerRegistryInterface
{
    /** @var array<string, PaymentProductHandlerInterface> */
    private array $handlers = [];

    /** @var string[] */
    private readonly array $codes;

    /**
     * @param ContainerInterface $locator  Lazy locator indexed by handler code
     * @param iterable<string, PaymentProductHandlerInterface> $handlers Tagged iterator (code => handler)
     */
    public function __construct(
        private readonly ContainerInterface $locator,
        iterable $handlers,
    ) {
        $codes = [];
        foreach (array_keys(iterator_to_array($handlers)) as $code) {
            $codes[] = $code;
        }
        $this->codes = $codes;
    }

    public function get(string $code): PaymentProductHandlerInterface
    {
        if (!$this->locator->has($code)) {
            throw new InvalidArgumentException(sprintf(
                'Payment product handler "%s" not found. Available: %s',
                $code,
                implode(', ', $this->codes),
            ));
        }

        /** @var PaymentProductHandlerInterface $handler */
        $handler = $this->locator->get($code);

        return $handler;
    }

    public function getForPaymentProduct(string $paymentProduct): ?PaymentProductHandlerInterface
    {
        foreach ($this->getAll() as $handler) {
            if ($handler->supports($paymentProduct)) {
                return $handler;
            }
        }

        return null;
    }

    public function getForPaymentMethod(PaymentMethodInterface $paymentMethod): ?PaymentProductHandlerInterface
    {
        $paymentProduct = $paymentMethod->getGatewayConfig()?->getConfig()['payment_product'] ?? null;
        if (!is_string($paymentProduct)) {
            return null;
        }

        return $this->getForPaymentProduct($paymentProduct);
    }

    public function getAll(): array
    {
        if (empty($this->handlers)) {
            foreach ($this->codes as $code) {
                /** @var PaymentProductHandlerInterface $handler */
                $handler = $this->locator->get($code);
                $this->handlers[$code] = $handler;
            }
        }

        return $this->handlers;
    }
}
