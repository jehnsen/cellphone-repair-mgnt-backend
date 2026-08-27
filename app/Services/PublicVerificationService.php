<?php

namespace App\Services;

use App\Models\RepairTicket;
use App\Models\VerificationToken;
use App\Support\Api\ApiException;
use App\Support\Api\ErrorCode;

/**
 * Backs the one unauthenticated endpoint in the whole API
 * (`GET /public/verify/{token}`, docs/design/01-domain-design.md §6) — a
 * customer or downstream buyer proving a device genuinely passed through
 * this shop's chain of custody. Strictly redacted by design: no customer
 * PII, no claim_code (that's the pickup credential, not a public proof),
 * no unlock info, no pricing, no technician identity. Rate-limited via the
 * `public-verify` limiter (AppServiceProvider), not auth.
 */
class PublicVerificationService
{
    public function findByToken(string $token): RepairTicket
    {
        $verification = VerificationToken::where('token', $token)->whereNull('revoked_at')->first();

        if ($verification === null) {
            throw new ApiException(ErrorCode::NotFound, 'This verification link is invalid or has been revoked.');
        }

        return RepairTicket::with(['branch', 'warranty'])->findOrFail($verification->repair_ticket_id);
    }
}
