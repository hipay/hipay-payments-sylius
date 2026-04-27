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

namespace HiPay\SyliusHiPayPlugin\Payment;

use HiPay\SyliusHiPayPlugin\Payment\PaymentTransitions as HiPayPaymentTransitionsAlias;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentTransitions;

/**
 * HiPay transaction status codes (from server-to-server notifications) mapped to Sylius payment transitions.
 *
 * @see https://developer.hipay.com/payment-fundamentals/essentials/transaction-status
 */
enum HiPayStatus: int
{
    // Informational
    case TransactionCreated = 101;
    case CardholderEnrolled = 103;
    case CardholderNotEnrolled = 104;
    case UnableToAuthenticate = 105;
    case CardholderAuthenticated = 106;
    case AuthenticationAttempted = 107;
    case CouldNotAuthenticate = 108;
    case AmountCollected = 120;
    case AmountPartiallyCollected = 121;
    case TransactionSettled = 122;
    case TransactionPartiallySettled = 123;
    case DebitIssued = 131;
    case PartialDebitIssued = 132;
    case AuthenticationRequested = 140;
    case Authenticated = 141;
    case AcquirerFound = 150;
    case Unpaid = 129;
    case AcquirerNotFound = 151;
    case EnrollmentStatusUnknown = 160;
    case RiskAccepted = 161;

    // Failure / decline
    case AuthenticationFailed = 109;
    case Blocked = 110;
    case Denied = 111;
    case Refused = 113;
    case Expired = 114;
    case Cancelled = 115;
    case AuthorizationRefused = 163;
    case CaptureRefused = 173;
    case SoftDeclined = 178;

    // Success / in progress
    case AuthorizedAndPending = 112;
    case Authorized = 116;
    case CaptureRequested = 117;
    case Captured = 118;
    case PartiallyCaptured = 119;

    // Pending / Processing
    case AuthorizationRequested = 142;
    case ReferenceRendered = 144;
    case InProgress = 172;
    case AwaitingTerminal = 174;
    case PendingPayment = 200;

    // Refund / Credits
    case RefundRequested = 124;
    case Refunded = 125;
    case PartiallyRefunded = 126;
    case RefundRefused = 165;
    case CardholderCredit = 166;
    case DebitedCardholderCredit = 168;
    case CreditRequested = 169;
    case PartiallyRefundByRdr = 182;
    case RefundByRdr = 183;

    // Chargeback / Dispute
    case DisputeLost = 134;
    case PartiallyChargeback = 180;
    case Chargeback = 181;

    // Cancellations
    case AuthorizationCancelled = 143;
    case AuthorizationCancellationRequested = 175;

    /**
     * Returns the Sylius payment state machine transition for this status.
     * Returns null if the HiPay status shouldn't trigger a main state machine transition.
     */
    public function toSyliusTransition(): ?string
    {
        return match ($this) {
            self::AuthorizedAndPending => HiPayPaymentTransitionsAlias::HOLD,

            // Authorized
            self::Authorized => PaymentTransitions::TRANSITION_AUTHORIZE,

            // Completed
            self::Captured,
            self::PartiallyCaptured => PaymentTransitions::TRANSITION_COMPLETE,

            // Failed (spec HIPA24: payment state → failed)
            self::AuthenticationFailed,
            self::Refused,
            self::AuthorizationRefused,
            self::CaptureRefused,
            self::RefundRefused,
            self::Expired => PaymentTransitions::TRANSITION_FAIL,

            // Customer-initiated cancellation: semantically distinct from a
            // technical failure (refused, blocked, etc.). Mapped to `cancel`
            // so the admin sees "Cancelled" instead of "Failed" for voluntary
            // abandonments (e.g. iDEAL "Cancelled by customer").
            self::Blocked,
            self::Denied,
            self::AuthorizationCancelled,
            self::Cancelled => PaymentTransitions::TRANSITION_CANCEL,

            // Refund (including chargebacks and lost disputes)
            self::Refunded,
            self::PartiallyRefunded => PaymentTransitions::TRANSITION_REFUND,

            // No state change: informational or no-op
            self::SoftDeclined,
            self::InProgress,
            self::AuthorizationRequested,
            self::ReferenceRendered,
            self::RefundRequested,
            self::DisputeLost,
            self::CardholderCredit,
            self::TransactionCreated,
            self::CardholderEnrolled,
            self::CardholderNotEnrolled,
            self::UnableToAuthenticate,
            self::CardholderAuthenticated,
            self::AuthenticationAttempted,
            self::CouldNotAuthenticate,
            self::AmountCollected,
            self::AmountPartiallyCollected,
            self::TransactionSettled,
            self::TransactionPartiallySettled,
            self::DebitIssued,
            self::PartialDebitIssued,
            self::AuthenticationRequested,
            self::Authenticated,
            self::AcquirerFound,
            self::AcquirerNotFound,
            self::EnrollmentStatusUnknown,
            self::RiskAccepted,
            self::Unpaid,
            self::CaptureRequested,
            self::DebitedCardholderCredit,
            self::PartiallyChargeback,
            self::Chargeback,
            self::PartiallyRefundByRdr,
            self::RefundByRdr,
            self::PendingPayment,
            self::AwaitingTerminal,
            self::CreditRequested,
            self::AuthorizationCancellationRequested => null,
        };
    }

    public function toPaymentRequestAction(): string
    {
        return match ($this) {
            // Authorized
            self::Authorized => PaymentRequestInterface::ACTION_AUTHORIZE,

            // Capture
            self::Captured,
            self::PartiallyCaptured => PaymentRequestInterface::ACTION_CAPTURE,

            // Cancel / Failure
            self::Cancelled,
            self::Expired,
            self::AuthenticationFailed,
            self::Refused,
            self::AuthorizationRefused,
            self::CaptureRefused,
            self::Blocked,
            self::Denied,
            self::AuthorizationCancelled,
            self::RefundRefused => PaymentRequestInterface::ACTION_CANCEL,

            // Refund
            self::Refunded,
            self::PartiallyRefunded => PaymentRequestInterface::ACTION_REFUND,

            // Status (informational, pending, no-op)
            self::RefundRequested,
            self::CreditRequested,
            self::CaptureRequested,
            self::TransactionCreated,
            self::CardholderEnrolled,
            self::CardholderNotEnrolled,
            self::UnableToAuthenticate,
            self::CardholderAuthenticated,
            self::AuthenticationAttempted,
            self::CouldNotAuthenticate,
            self::AmountCollected,
            self::AmountPartiallyCollected,
            self::TransactionSettled,
            self::TransactionPartiallySettled,
            self::DebitIssued,
            self::DisputeLost,
            self::PartialDebitIssued,
            self::AuthenticationRequested,
            self::Authenticated,
            self::AcquirerFound,
            self::AcquirerNotFound,
            self::EnrollmentStatusUnknown,
            self::RiskAccepted,
            self::CardholderCredit,
            self::AuthorizedAndPending,
            self::AuthorizationRequested,
            self::ReferenceRendered,
            self::InProgress,
            self::AwaitingTerminal,
            self::PendingPayment,
            self::PartiallyChargeback,
            self::Chargeback,
            self::PartiallyRefundByRdr,
            self::RefundByRdr,
            self::Unpaid,
            self::DebitedCardholderCredit,
            self::AuthorizationCancellationRequested,
            self::SoftDeclined => PaymentRequestInterface::ACTION_NOTIFY,
        };
    }

    /**
     * Priority group assigned to a notification when buffered in
     * `hipay_pending_notification`. Grouping notifications by priority lets
     * the scheduler apply them to the state machine in a deterministic order
     * for a given transaction, independently of HiPay's callback delivery order.
     *
     * Lower number = processed sooner.
     *
     *  1. In progress (pending authorization / awaiting terminal / reference rendered)
     *  2. Failure statuses (authentication failed, blocked, denied, refused, expired, dispute lost, soft decline)
     *  3. Chargeback
     *  4. Authorized
     *  5. Capture requested / capture refused
     *  6. Partially captured
     *  7. Paid (captured + cardholder credit debited)
     *  8. Refund requested / refund refused
     *  9. Partially refunded
     * 10. Refunded
     * 11. Cancellations (customer cancel, authorization cancelled, cancellation requested)
     */
    public function toNotificationPriority(): int
    {
        return match ($this) {
            // 1. In progress
            self::AuthorizedAndPending,
            self::AuthorizationRequested,
            self::ReferenceRendered,
            self::CreditRequested,
            self::InProgress,
            self::AwaitingTerminal,
            self::PendingPayment => 1,

            // 2. Failure statuses
            self::AuthenticationFailed,
            self::Blocked,
            self::Denied,
            self::Refused,
            self::Expired,
            self::DisputeLost,
            self::SoftDeclined => 2,

            // 3. Chargeback (partial chargeback shares the group; it follows the main capture cycle)
            self::Chargeback,
            self::PartiallyChargeback => 3,

            // 4. Authorized
            self::Authorized => 4,

            // 5. Capture requested / refused
            self::CaptureRequested,
            self::CaptureRefused => 5,

            // 6. Partially captured
            self::PartiallyCaptured => 6,

            // 7. Paid
            self::Captured,
            self::CardholderCredit,
            self::DebitedCardholderCredit => 7,

            // 8. Refund requested / refused
            self::RefundRequested,
            self::RefundRefused => 8,

            // 9. Partially refunded
            self::PartiallyRefunded,
            self::PartiallyRefundByRdr => 9,

            // 10. Refunded
            self::Refunded,
            self::RefundByRdr => 10,

            // 11. Cancellations
            self::Cancelled,
            self::AuthorizationCancelled,
            self::AuthorizationCancellationRequested => 11,

            // Everything else: informational statuses that don't alter the state machine.
            // Last priority so they never delay a state-changing notification for the
            // same transaction.
            self::TransactionCreated,
            self::CardholderEnrolled,
            self::CardholderNotEnrolled,
            self::UnableToAuthenticate,
            self::CardholderAuthenticated,
            self::AuthenticationAttempted,
            self::CouldNotAuthenticate,
            self::AmountCollected,
            self::AmountPartiallyCollected,
            self::TransactionSettled,
            self::TransactionPartiallySettled,
            self::DebitIssued,
            self::PartialDebitIssued,
            self::AuthenticationRequested,
            self::Authenticated,
            self::AcquirerFound,
            self::AcquirerNotFound,
            self::EnrollmentStatusUnknown,
            self::RiskAccepted,
            self::Unpaid,
            self::AuthorizationRefused => 12,
        };
    }

    /**
     * Retrieves the corresponding Sylius state machine transition for a given HiPay status code.
     * Returns null if no transition is mapped or if the status is unknown.
     */
    public static function getSyliusTransition(?int $status): ?string
    {
        return self::tryFrom($status ?? 0)?->toSyliusTransition();
    }

    /**
     * Retrieves the corresponding PaymentRequest action for a given HiPay status code.
     * Returns null if the status is unknown (not present in the enum).
     */
    public static function getPaymentRequestAction(?int $status): ?string
    {
        return self::tryFrom($status ?? 0)?->toPaymentRequestAction();
    }

    /**
     * Returns the buffered-notification priority for a HiPay status code.
     * Unknown statuses fall into the lowest priority tier so they never delay
     * a known state-changing notification.
     */
    public static function getNotificationPriority(?int $status): int
    {
        return self::tryFrom($status ?? 0)?->toNotificationPriority() ?? 99;
    }

    /**
     * Whether the status code is known by the plugin (present in the enum).
     */
    public static function isKnown(?int $status): bool
    {
        return null !== self::tryFrom($status ?? 0);
    }
}
