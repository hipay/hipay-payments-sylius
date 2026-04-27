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

namespace HiPay\SyliusHiPayPlugin\OrderPay\Handler;

use Sylius\Bundle\CoreBundle\OrderPay\Handler\PaymentStateFlashHandlerInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;

/**
 * Decorates Sylius's PaymentStateFlashHandler to suppress the native "info"
 * flash messages for HiPay payments.
 *
 * Context (HIPASYLU001-104):
 * Sylius's PaymentRequestAfterPayResponseProvider always calls
 * PaymentStateFlashHandler::handle() after the gateway's HTTP response
 * provider, adding a generic "info" flash (e.g. "sylius.payment.completed"
 * or "sylius.payment.failed"). For HiPay, this causes:
 *   - Unnecessary blue "info" messages on the thank-you page for successful payments
 *   - Error messages displayed in blue instead of red
 *   - Duplicate messages when the plugin already adds its own "error" flash
 *
 * This decorator checks for the REQUEST_ATTR_HIPAY_PAYMENT request attribute
 * (set by HostedFieldsHttpResponseProvider) and skips the native flash
 * when a HiPay gateway handled the payment. Non-HiPay gateways are
 * unaffected and delegate to the original handler.
 */
final class HiPayPaymentStateFlashHandlerDecorator implements PaymentStateFlashHandlerInterface
{
    /**
     * Request attribute set by HostedFieldsHttpResponseProvider to signal
     * that the current payment was processed by a HiPay gateway.
     */
    public const REQUEST_ATTR_HIPAY_PAYMENT = '_hipay_payment';

    public function __construct(
        private readonly PaymentStateFlashHandlerInterface $decoratedHandler,
    ) {
    }

    public function handle(RequestConfiguration $requestConfiguration, string $state): void
    {
        $request = $requestConfiguration->getRequest();

        if (true === $request->attributes->get(self::REQUEST_ATTR_HIPAY_PAYMENT)) {
            return;
        }

        $this->decoratedHandler->handle($requestConfiguration, $state);
    }
}
