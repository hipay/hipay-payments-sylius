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

namespace HiPay\SyliusHiPayPlugin\Twig\Extension;

use HiPay\SyliusHiPayPlugin\Payment\Capture\PaymentCaptureEligibilityCheckerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PaymentCaptureExtension extends AbstractExtension
{
    public function __construct(
        private readonly PaymentCaptureEligibilityCheckerInterface $eligibilityChecker,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('hipay_payment_capturable', $this->isCapturable(...)),
        ];
    }

    public function isCapturable(PaymentInterface $payment): bool
    {
        return $this->eligibilityChecker->isEligible($payment);
    }
}
