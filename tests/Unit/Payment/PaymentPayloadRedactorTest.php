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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Payment;

use HiPay\SyliusHiPayPlugin\Payment\PaymentPayloadRedactor;
use PHPUnit\Framework\TestCase;

final class PaymentPayloadRedactorTest extends TestCase
{
    private PaymentPayloadRedactor $redactor;

    protected function setUp(): void
    {
        $this->redactor = new PaymentPayloadRedactor();
    }

    /**
     * V3 — the outbound order-request payload (camelCase container + `cardtoken`).
     */
    public function testItDropsTheCardTokenFromANestedCamelCasePaymentMethod(): void
    {
        $payload = [
            'orderid' => 'X-1',
            'amount' => 100,
            'paymentMethod' => [
                'cardtoken' => 'VICTIM_CARD_TOKEN_abc123',
                'pan' => '411111******1111',
            ],
            'one_click' => 1,
        ];

        $this->assertSame([
            'orderid' => 'X-1',
            'amount' => 100,
            'paymentMethod' => [],
            'one_click' => 1,
        ], $this->redactor->redact($payload));
    }

    /**
     * V8 — the inbound notification payload (snake_case container + `token`, plus root fields).
     */
    public function testItDropsCardDataFromASnakeCaseNotificationAtEveryLevel(): void
    {
        $payload = [
            'status' => 118,
            'card_holder' => 'JOHN DOE',
            'card_expiry_month' => '12',
            'card_expiry_year' => '2030',
            'card_expiry_date' => '12/2030',
            'payment_method' => [
                'token' => 'tok_123',
                'card_id' => 'cid_9',
                'pan' => '411111******1111',
                'card_number' => '4111111111111111',
                'cvc' => '123',
                'cvc_result' => 'M',
                'card_security_code' => '123',
                'brand' => 'VISA',
            ],
        ];

        $this->assertSame([
            'status' => 118,
            'payment_method' => [
                'brand' => 'VISA',
            ],
        ], $this->redactor->redact($payload));
    }

    public function testItAlsoCatchesCamelCaseExpiryVariants(): void
    {
        $payload = [
            'cardExpiryMonth' => '12',
            'cardExpiryYear' => '2030',
            'cardExpiryDate' => '12/2030',
            'cardHolder' => 'JOHN DOE',
            'cardNumber' => '4111111111111111',
            'keep' => 'me',
        ];

        $this->assertSame(['keep' => 'me'], $this->redactor->redact($payload));
    }

    public function testItLeavesAPayloadWithoutCardDataUntouched(): void
    {
        $payload = [
            'orderid' => 'X-1',
            'amount' => 100,
            'currency' => 'EUR',
            'customer' => ['email' => 'buyer@example.com'],
        ];

        $this->assertSame($payload, $this->redactor->redact($payload));
    }
}
