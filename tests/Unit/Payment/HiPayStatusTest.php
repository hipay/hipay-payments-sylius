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

use HiPay\SyliusHiPayPlugin\Payment\HiPayStatus;
use HiPay\SyliusHiPayPlugin\Payment\PaymentTransitions as HipayPaymentTransitions;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Payment\PaymentTransitions;

final class HiPayStatusTest extends TestCase
{
    /**
     * @dataProvider statusToTransitionProvider
     */
    public function testGetSyliusTransitionReturnsExpectedTransition(int $hipayStatus, ?string $expectedTransition): void
    {
        self::assertSame($expectedTransition, HiPayStatus::getSyliusTransition($hipayStatus));
    }

    /**
     * All HiPay transaction statuses sent by server-to-server notification (and related) mapped to Sylius transitions.
     *
     * @see https://developer.hipay.com/payment-fundamentals/essentials/transaction-status
     *
     * @return iterable<string, array{int, string|null}>
     */
    public static function statusToTransitionProvider(): iterable
    {
        // Informational – no state change
        yield '101 Transaction Created → null' => [101, null];
        yield '103 Cardholder Enrolled → null' => [103, null];
        yield '104 Cardholder Not Enrolled → null' => [104, null];
        yield '105 Unable to Authenticate → null' => [105, null];
        yield '106 Cardholder Authenticated → null' => [106, null];
        yield '107 Authentication Attempted → null' => [107, null];
        yield '108 Could Not Authenticate → null' => [108, null];
        yield '120 Amount Collected → null' => [120, null];
        yield '121 Amount Partially Collected → null' => [121, null];
        yield '122 Transaction Settled → null' => [122, null];
        yield '123 Transaction Partially Settled → null' => [123, null];
        yield '131 Debit Issued → null' => [131, null];
        yield '132 Partial Debit Issued → null' => [132, null];
        yield '140 Authentication Requested → null' => [140, null];
        yield '141 Authenticated → null' => [141, null];
        yield '150 Acquirer Found → null' => [150, null];
        yield '151 Acquirer Not Found → null' => [151, null];
        yield '160 Enrollment Status Unknown → null' => [160, null];
        yield '161 Risk Accepted → null' => [161, null];

        // Failure / decline (HIPA24: only explicit refusal/expiry map to fail; blocked/denied/soft-decline are no-op)
        yield '109 Authentication Failed → fail' => [109, PaymentTransitions::TRANSITION_FAIL];
        yield '110 Blocked → cancel' => [110, PaymentTransitions::TRANSITION_CANCEL];
        yield '111 Denied → cancel' => [111, PaymentTransitions::TRANSITION_CANCEL];
        yield '113 Refused → fail' => [113, PaymentTransitions::TRANSITION_FAIL];
        yield '114 Expired → fail' => [114, PaymentTransitions::TRANSITION_FAIL];
        yield '115 Cancelled → cancel' => [115, PaymentTransitions::TRANSITION_CANCEL];
        yield '163 Authorization Refused → fail' => [163, PaymentTransitions::TRANSITION_FAIL];
        yield '173 Capture Refused → fail' => [173, PaymentTransitions::TRANSITION_FAIL];
        yield '178 Soft declined → null (no transition)' => [178, null];

        // Success / in progress
        yield '112 Authorized and Pending → null (no transition)' => [112, HipayPaymentTransitions::HOLD];
        yield '116 Authorized → authorize' => [116, PaymentTransitions::TRANSITION_AUTHORIZE];
        yield '117 Capture Requested → null (no transition)' => [117, null];
        yield '118 Captured → complete' => [118, PaymentTransitions::TRANSITION_COMPLETE];
        yield '119 Partially Captured → complete' => [119, PaymentTransitions::TRANSITION_COMPLETE];

        // Pending / processing (informational: no main state machine transition)
        yield '142 Authorization Requested → null (no transition)' => [142, null];
        yield '144 Reference rendered → null (no transition)' => [144, null];
        yield '172 In progress (MixPayment) → null (no transition)' => [172, null];
        yield '174 Awaiting Terminal → null (no transition)' => [174, null];
        yield '200 Pending Payment → null (no transition)' => [200, null];

        // Refund / credits
        yield '124 Refund Requested → null (no transition)' => [124, null];
        yield '125 Refunded → refund' => [125, PaymentTransitions::TRANSITION_REFUND];
        yield '126 Partially Refunded → refund' => [126, PaymentTransitions::TRANSITION_REFUND];
        yield '165 Refund Refused → fail' => [165, PaymentTransitions::TRANSITION_FAIL];
        yield '166 Cardholder credit → null (no transition)' => [166, null];
        yield '168 Debited cardholder credit → null (no transition)' => [168, null];
        yield '169 Credit requested → null (no transition)' => [169, null];
        yield '182 Partially refund by RDR → null (no transition)' => [182, null];
        yield '183 Refund by RDR → null (no transition)' => [183, null];

        // Chargeback / dispute (129 is informational per HIPA24)
        yield '129 Unpaid → informational (null)' => [129, null];
        yield '134 Dispute lost → null (no transition)' => [134, null];
        yield '180 Partially chargeback → null (no transition)' => [180, null];
        yield '181 Chargeback → null (no transition)' => [181, null];

        // Cancellations
        yield '143 Authorization Cancelled → cancel' => [143, PaymentTransitions::TRANSITION_CANCEL];
        yield '175 Authorization cancellation requested → null (no transition)' => [175, null];
    }

    public function testGetSyliusTransitionReturnsNullForUnknownStatus(): void
    {
        self::assertNull(HiPayStatus::getSyliusTransition(999));
        self::assertNull(HiPayStatus::getSyliusTransition(0));
        self::assertNull(HiPayStatus::getSyliusTransition(null));
    }

    public function testGetSyliusTransitionAcceptsStringStatusFromFormPayload(): void
    {
        self::assertSame(PaymentTransitions::TRANSITION_COMPLETE, HiPayStatus::getSyliusTransition(118));
    }

    public function testIsKnownReturnsTrueForDefinedStatus(): void
    {
        self::assertTrue(HiPayStatus::isKnown(101));
        self::assertTrue(HiPayStatus::isKnown(118));
        self::assertTrue(HiPayStatus::isKnown(163));
    }

    public function testIsKnownReturnsFalseForUndefinedStatus(): void
    {
        self::assertFalse(HiPayStatus::isKnown(999));
        self::assertFalse(HiPayStatus::isKnown(0));
        self::assertFalse(HiPayStatus::isKnown(null));
    }

    /**
     * @dataProvider statusToPriorityProvider
     */
    public function testGetNotificationPriorityReturnsExpectedGroup(int $hipayStatus, int $expectedPriority): void
    {
        self::assertSame($expectedPriority, HiPayStatus::getNotificationPriority($hipayStatus));
    }

    /**
     * Priority groups ensure that, for a given transaction, state-changing
     * notifications are applied in a deterministic order regardless of HiPay's
     * callback delivery order.
     *
     * @return iterable<string, array{int, int}>
     */
    public static function statusToPriorityProvider(): iterable
    {
        // 1. In progress
        yield '112 Authorized and Pending → 1' => [112, 1];
        yield '142 Authorization Requested → 1' => [142, 1];
        yield '144 Reference rendered → 1' => [144, 1];
        yield '169 Credit requested → 1' => [169, 1];
        yield '172 In Progress → 1' => [172, 1];
        yield '174 Awaiting Terminal → 1' => [174, 1];
        yield '200 Pending Payment → 1' => [200, 1];

        // 2. Failure
        yield '109 Authentication Failed → 2' => [109, 2];
        yield '110 Blocked → 2' => [110, 2];
        yield '111 Denied → 2' => [111, 2];
        yield '113 Refused → 2' => [113, 2];
        yield '114 Expired → 2' => [114, 2];
        yield '134 Dispute lost → 2' => [134, 2];
        yield '178 Soft declined → 2' => [178, 2];

        // 3. Chargeback
        yield '180 Partially chargeback → 3' => [180, 3];
        yield '181 Chargeback → 3' => [181, 3];

        // 4. Authorized
        yield '116 Authorized → 4' => [116, 4];

        // 5. Capture requested / refused
        yield '117 Capture Requested → 5' => [117, 5];
        yield '173 Capture Refused → 5' => [173, 5];

        // 6. Partially captured
        yield '119 Partially Captured → 6' => [119, 6];

        // 7. Paid
        yield '118 Captured → 7' => [118, 7];
        yield '166 Cardholder credit → 7' => [166, 7];
        yield '168 Debited cardholder credit → 7' => [168, 7];

        // 8. Refund requested / refused
        yield '124 Refund Requested → 8' => [124, 8];
        yield '165 Refund Refused → 8' => [165, 8];

        // 9. Partially refunded
        yield '126 Partially Refunded → 9' => [126, 9];
        yield '182 Partially refund by RDR → 9' => [182, 9];

        // 10. Refunded
        yield '125 Refunded → 10' => [125, 10];
        yield '183 Refund by RDR → 10' => [183, 10];

        // 11. Cancellations
        yield '115 Cancelled → 11' => [115, 11];
        yield '143 Authorization Cancelled → 11' => [143, 11];
        yield '175 Authorization Cancellation Requested → 11' => [175, 11];

        // 12. Informational (no state-changing effect)
        yield '101 Transaction Created → 12' => [101, 12];
        yield '122 Transaction Settled → 12' => [122, 12];
        yield '163 Authorization Refused → 12' => [163, 12];
    }

    public function testGetNotificationPriorityReturnsLowestTierForUnknownStatus(): void
    {
        self::assertSame(99, HiPayStatus::getNotificationPriority(9999));
        self::assertSame(99, HiPayStatus::getNotificationPriority(null));
        self::assertSame(99, HiPayStatus::getNotificationPriority(0));
    }
}
