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

use HiPay\SyliusHiPayPlugin\Provider\CheckoutJsSdkConfigFactory;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class HipayExtension extends AbstractExtension
{
    public const HIPAY_SDK_JS_URL = 'https://libs.hipay.com/js/sdkjs.js';

    public const HIPAY_INTEGRITY_HASH_URL = 'https://libs.hipay.com/js/sdkjs.integrity';

    private ?string $integrityHash = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CheckoutJsSdkConfigFactory $checkoutJsSdkConfigFactory,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('hipay_js_sdk_url', (static fn (): string => self::HIPAY_SDK_JS_URL)),
            new TwigFunction('hipay_integrity_hash', [$this, 'getIntegrityHash']),
            new TwigFunction('hipay_checkout_js_sdk_config', [$this, 'getCheckoutJsSdkConfigForOrder']),
        ];
    }

    public function getIntegrityHash(): string
    {
        if (null !== $this->integrityHash) {
            return $this->integrityHash;
        }

        $response = $this->httpClient->request('GET', self::HIPAY_INTEGRITY_HASH_URL);
        $this->integrityHash = $response->getContent();

        return $this->integrityHash;
    }

    /**
     * Same payload as {@see HiPayCheckoutComponent::getJsSdkConfig()} for the order’s last payment (thank-you, etc.).
     *
     * @return array<string, mixed>
     */
    public function getCheckoutJsSdkConfigForOrder(?OrderInterface $order): array
    {
        if (null === $order) {
            return [];
        }

        $payment = $order->getLastPayment();
        if (null === $payment) {
            return [];
        }
        /** @var PaymentMethodInterface|null $method */
        $method = $payment->getMethod();
        if (null === $method) {
            return [];
        }

        return $this->checkoutJsSdkConfigFactory->create($method, $payment);
    }
}
