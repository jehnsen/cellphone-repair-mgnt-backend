<?php

namespace App\Support\Api;

/**
 * The full error code catalogue — see docs/design/01-domain-design.md §5.
 * Clients switch on `code`, never on `message`; codes are part of the API
 * contract and cannot change without a version bump.
 */
enum ErrorCode: string
{
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';

    case UnitAlreadySold = 'UNIT_ALREADY_SOLD';
    case InvalidStatusTransition = 'INVALID_STATUS_TRANSITION';
    case ImeiMismatch = 'IMEI_MISMATCH';
    case ShiftNotOpen = 'SHIFT_NOT_OPEN';
    case IdempotencyConflict = 'IDEMPOTENCY_CONFLICT';
    case AcquisitionImeiFlagged = 'ACQUISITION_IMEI_FLAGGED';
    case PaymentSumMismatch = 'PAYMENT_SUM_MISMATCH';
    case SyncConflict = 'SYNC_CONFLICT';

    case ValidationFailed = 'VALIDATION_FAILED';
    case InsufficientStock = 'INSUFFICIENT_STOCK';
    case InvalidImei = 'INVALID_IMEI';
    case InvalidPhMobile = 'INVALID_PH_MOBILE';

    case RateLimited = 'RATE_LIMITED';
    case InternalError = 'INTERNAL_ERROR';
    case ServiceUnavailable = 'SERVICE_UNAVAILABLE';

    /** HTTP status this code renders with unless a throw site overrides it. */
    public function defaultStatus(): int
    {
        return match ($this) {
            self::Unauthenticated => 401,
            self::Forbidden => 403,
            self::NotFound => 404,
            self::MethodNotAllowed => 405,
            self::UnitAlreadySold,
            self::InvalidStatusTransition,
            self::ImeiMismatch,
            self::ShiftNotOpen,
            self::IdempotencyConflict,
            self::AcquisitionImeiFlagged,
            self::PaymentSumMismatch,
            self::SyncConflict => 409,
            self::ValidationFailed,
            self::InsufficientStock,
            self::InvalidImei,
            self::InvalidPhMobile => 422,
            self::RateLimited => 429,
            self::InternalError => 500,
            self::ServiceUnavailable => 503,
        };
    }

    /**
     * Best-effort mapping from a raw HTTP status (framework-level exceptions
     * we didn't throw ourselves) to a cataloged code. Returns null when the
     * status has no catalogued equivalent — the caller should render a
     * generic `HTTP_{status}` code rather than misrepresent it as one of
     * these.
     */
    public static function fromHttpStatus(int $status): ?self
    {
        return match ($status) {
            401 => self::Unauthenticated,
            403 => self::Forbidden,
            404 => self::NotFound,
            405 => self::MethodNotAllowed,
            422 => self::ValidationFailed,
            429 => self::RateLimited,
            default => $status >= 500 ? self::InternalError : null,
        };
    }

    public function defaultMessage(): string
    {
        return match ($this) {
            self::Unauthenticated => 'Authentication is required to access this resource.',
            self::Forbidden => 'You do not have permission to perform this action.',
            self::NotFound => 'The requested resource could not be found.',
            self::MethodNotAllowed => 'This HTTP method is not supported on this route.',
            self::UnitAlreadySold => 'This serialized unit is no longer available.',
            self::InvalidStatusTransition => 'This status transition is not allowed.',
            self::ImeiMismatch => 'The scanned IMEI does not match the expected device.',
            self::ShiftNotOpen => 'This action requires an open shift.',
            self::IdempotencyConflict => 'This Idempotency-Key was already used with a different request body.',
            self::AcquisitionImeiFlagged => 'This acquisition cannot be completed while its IMEI check is flagged.',
            self::PaymentSumMismatch => 'The sum of payments does not match the total due.',
            self::SyncConflict => 'This offline operation conflicts with server state and needs manual resolution.',
            self::ValidationFailed => 'The given data was invalid.',
            self::InsufficientStock => 'There is not enough stock on hand for this operation.',
            self::InvalidImei => 'The given IMEI is not valid.',
            self::InvalidPhMobile => 'The given mobile number is not a valid Philippine mobile number.',
            self::RateLimited => 'Too many requests. Please slow down and try again shortly.',
            self::InternalError => 'Something went wrong on our end.',
            self::ServiceUnavailable => 'A required dependency is currently unavailable.',
        };
    }
}
