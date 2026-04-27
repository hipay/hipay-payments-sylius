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

namespace HiPay\SyliusHiPayPlugin\Logging;

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use RedactSensitive\RedactSensitiveProcessor;
use Stringable;

final class HiPayLogger implements HiPayLoggerInterface
{
    use LoggerTrait;

    private const CUSTOMER_BILLING_SENSITIVE_INFO = [
        'birthdate' => 0,
        'city' => 0,
        'country' => 0,
        'email' => 0,
        'firstname' => 0,
        'gender' => 0,
        'lastname' => 0,
        'msisdn' => 0,
        'phone' => 0,
        'recipientinfo' => 0,
        'state' => 0,
        'streetaddress' => 0,
        'streetaddress2' => 0,
        'zipcode' => 0,
    ];

    private const CUSTOMER_SHIPPING_SENSITIVE_INFO = [
        'shipto_city' => 0,
        'shipto_country' => 0,
        'shipto_firstname' => 0,
        'shipto_gender' => 0,
        'shipto_house_number' => 0,
        'shipto_lastname' => 0,
        'shipto_msisdn' => 0,
        'shipto_phone' => 0,
        'shipto_recipientinfo' => 0,
        'shipto_state' => 0,
        'shipto_streetaddress' => 0,
        'shipto_streetaddress2' => 0,
        'shipto_zipcode' => 0,
    ];

    private const PAYMENT_SENSITIVE_METHOD = [
        'authentication_indicator' => 0,
        'cardHolder' => 0,
        'cardtoken' => 0,
        'cvc' => 0,
        'cvv' => 0,
        'eci' => 0,
        'pan' => 0,
        'phone' => 0,
        'shipto_gender' => 0,
        'shipto_msisdn' => 0,
        'shipto_phone' => 0,
        'token' => 0,
    ];

    private const SENSITIVE_KEYS = [
        'authentication_token' => 0,
        'authenticationToken' => 0,
        'authorization_code' => 0,
        'authorizationCode' => 0,
        'birthdate' => 0,
        'card_expiry_date' => 0,
        'card_expiry_month' => 0,
        'card_expiry_year' => 0,
        'card_holder' => 0,
        'card_holder_address' => 0,
        'card_holder_city' => 0,
        'card_holder_country' => 0,
        'card_holder_name' => 0,
        'card_holder_state' => 0,
        'card_holder_zip_code' => 0,
        'card_id' => 0,
        'card_number' => 0,
        'card_security_code' => 0,
        'cardHolder' => 0,
        'cardtoken' => 0,
        'cid' => 0,
        'city' => 0,
        'customerBillingInfo' => self::CUSTOMER_BILLING_SENSITIVE_INFO,
        'customerShippingInfo' => self::CUSTOMER_SHIPPING_SENSITIVE_INFO,
        'cvc' => 0,
        'cvc_result' => 0,
        'cvcResult' => 0,
        'cvv' => 0,
        'device_fingerprint' => 0,
        'deviceFingerprint' => 0,
        'email' => 0,
        'firstname' => 0,
        'ip_address' => 0,
        'ipAddress' => 0,
        'ipaddr' => 0,
        'lastname' => 0,
        'msisdn' => 0,
        'pan' => 0,
        'payment_method' => self::PAYMENT_SENSITIVE_METHOD,
        'paymentMethod' => self::PAYMENT_SENSITIVE_METHOD,
        'phone' => 0,
        'recipientinfo' => 0,
        'shipto_city' => 0,
        'shipto_firstname' => 0,
        'shipto_house_number' => 0,
        'shipto_lastname' => 0,
        'shipto_msisdn' => 0,
        'shipto_phone' => 0,
        'shipto_recipientinfo' => 0,
        'shipto_state' => 0,
        'shipto_streetaddress' => 0,
        'shipto_streetaddress2' => 0,
        'shipto_zipcode' => 0,
        'streetaddress' => 0,
        'streetaddress2' => 0,
        'token' => 0,
        'xid' => 0,
        'zipcode' => 0,
    ];

    private ?AccountInterface $account = null;

    public function __construct(
        private readonly LoggerInterface $innerLogger,
        private readonly LoggerInterface $logger,
    ) {
        $coerceNullForNestedRedaction = new CoerceNullForNestedRedactionProcessor(self::SENSITIVE_KEYS);
        $redactSensitive = new RedactSensitiveProcessor(self::SENSITIVE_KEYS);
        // @phpstan-ignore-next-line
        $this->innerLogger->pushProcessor($redactSensitive);
        // @phpstan-ignore-next-line
        $this->innerLogger->pushProcessor($coerceNullForNestedRedaction);
        // @phpstan-ignore-next-line
        $this->logger->pushProcessor($redactSensitive);
        // @phpstan-ignore-next-line
        $this->logger->pushProcessor($coerceNullForNestedRedaction);
    }

    public function setAccount(?AccountInterface $account): void
    {
        $this->account = $account;
    }

    /**
     * @inheritdoc
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if ($this->shouldLog()) {
            $this->innerLogger->log($level, $message, $context);

            return;
        }
        $this->logger->log($level, $message, $context);
    }

    protected function shouldLog(): bool
    {
        return null !== $this->account && $this->account->isDebugMode();
    }
}
