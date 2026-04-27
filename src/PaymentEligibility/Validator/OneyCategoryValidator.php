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

namespace HiPay\SyliusHiPayPlugin\PaymentEligibility\Validator;

use HiPay\SyliusHiPayPlugin\PaymentEligibility\PaymentEligibilityValidationResult;
use HiPay\SyliusHiPayPlugin\PaymentEligibility\PaymentEligibilityValidatorInterface;
use HiPay\SyliusHiPayPlugin\Provider\OneyCategoryProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\PaymentInterface;

class OneyCategoryValidator implements PaymentEligibilityValidatorInterface
{
    private string $code = 'oney';

    protected string $message = 'sylius_hipay_plugin.checkout.oney.category_mapping_invalid';

    public function __construct(
        private readonly OneyCategoryProviderInterface $oneyCategoryProvider,
    ) {
    }

    public function validate(?PaymentInterface $payment): ?PaymentEligibilityValidationResult
    {
        /** @var OrderInterface|null $order */
        $order = $payment?->getOrder();
        if (null === $order) {
            return null;
        }

        $validProducts = true;
        /** @var OrderItemInterface $item */
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            if (null === $product) {
                continue;
            }

            $oneyCategory = $this->oneyCategoryProvider->getByProduct($product);
            if (null === $oneyCategory) {
                $validProducts = false;

                break;
            }
        }

        return true === $validProducts ? null : new PaymentEligibilityValidationResult($this->message);
    }

    public function supports(string $paymentProduct): bool
    {
        return $paymentProduct === $this->code;
    }
}
