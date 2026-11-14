<?php

namespace App\Services\SanctumScan;

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
                'user_id'              => $userId,
                'method'               => $method,
                'reference'            => $reference ? trim($reference) : null,
                'review_status'        => $status,
                'summary'              => trim($summary),
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at'          => now(),
                'created_at'           => now(),
            ]);

            // Stamp the user row so the buyer gate can use a single indexed column.
            if ($status === 'verified') {
                $user->forceFill([
                    'identity_verified_at'          => now(),
                    'identity_verification_method'  => $method,
                ])->save();
            } elseif ($user->identity_verified_at !== null) {
                // A subsequent rejected or unable_to_verify check clears the stamp.
                $user->forceFill([
                    'identity_verified_at'         => null,
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
     * Returns true when the gate is enabled and the user has not been verified.
     * Returns false when the gate is disabled or the user is verified (allow through).
     */
    public function buyerIsBlocked(User $user): bool
    {
        $gateEnabled = (bool) optional(\App\Models\Admin\BasicSettings::first())->sanctum_exchange_identity_gate;

        if (! $gateEnabled) {
            return false;
        }

        return $user->identity_verified_at === null;
    }
}
