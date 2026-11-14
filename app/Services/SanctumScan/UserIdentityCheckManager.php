<?php

namespace App\Services\SanctumScan;

use App\Models\Admin\BasicSettings;
use App\Models\SanctumScanUserIdentityCheck;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserIdentityCheckManager
{
    public const METHODS = [
        'national_id_review',
        'passport_review',
        'utility_bill_review',
        'in_person_verification',
        'third_party_kyc',
        'other',
    ];

    public const STATUSES = ['verified', 'rejected', 'unable_to_verify'];

    public function record(
        int $userId,
        int $adminId,
        string $method,
        ?string $reference,
        string $status,
        string $summary
    ): SanctumScanUserIdentityCheck|string {
        if (! in_array($method, self::METHODS, true)) {
            return 'invalid_identity_check_method';
        }

        if (! in_array($status, self::STATUSES, true)) {
            return 'invalid_identity_check_status';
        }

        if (trim($summary) === '') {
            return 'invalid_identity_check_summary';
        }

        return DB::transaction(function () use ($userId, $adminId, $method, $reference, $status, $summary) {
            $user = User::query()
                ->whereKey($userId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->firstOrFail();

            $check = SanctumScanUserIdentityCheck::create([
                'user_id' => $userId,
                'method' => $method,
                'reference' => $reference !== null && trim($reference) !== '' ? trim($reference) : null,
                'review_status' => $status,
                'summary' => trim($summary),
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
                'created_at' => now(),
            ]);

            // The user row is the current-state projection; the check above remains the immutable history.
            if ($status === 'verified') {
                $user->forceFill([
                    'identity_verified_at' => now(),
                    'identity_verification_method' => $method,
                ])->save();
            } else {
                $user->forceFill([
                    'identity_verified_at' => null,
                    'identity_verification_method' => null,
                ])->save();
            }

            return $check->fresh();
        });
    }

    public function checksForUser(int $userId)
    {
        return SanctumScanUserIdentityCheck::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Must be called inside the exchange-acceptance transaction.
     * Lock settings before the buyer so gate changes and identity reviews serialize
     * against the acceptance decision. The merchant never calls this method.
     */
    public function buyerIsBlocked(User $user): bool
    {
        $settings = BasicSettings::query()
            ->lockForUpdate()
            ->first();

        if (! (bool) ($settings?->sanctum_exchange_identity_gate ?? false)) {
            return false;
        }

        $currentBuyer = User::query()
            ->whereKey($user->getKey())
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        return $currentBuyer === null || $currentBuyer->identity_verified_at === null;
    }
}
