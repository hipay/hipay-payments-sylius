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
use HiPay\SyliusHiPayPlugin\Provider\OneyShippingMethodProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

class OneyShippingMethodValidator implements PaymentEligibilityValidatorInterface
{
    private string $code = 'oney';

    protected string $message = 'sylius_hipay_plugin.checkout.oney.shiping_method_mapping_invalid';

    public function __construct(
        private readonly OneyShippingMethodProviderInterface $oneyShippingMethodProvider,
    ) {
    }

    public function validate(?PaymentInterface $payment): ?PaymentEligibilityValidationResult
    {
        /** @var OrderInterface|null $order */
        $order = $payment?->getOrder();
        if (null === $order) {
            return null;
        }

        $oneyShippingMethod = $this->oneyShippingMethodProvider->getByOrder($order);

        return null !== $oneyShippingMethod ? null : new PaymentEligibilityValidationResult($this->message);
    }

    public function supports(string $paymentProduct): bool
    {
        return $paymentProduct === $this->code;
    }
}
