<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\BasicSettings;
use App\Models\SanctumScanUserIdentityCheck;
use App\Services\SanctumScan\UserIdentityCheckManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SanctumScanUserIdentityReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'sometimes|nullable|integer|min:1',
        ]);

        $query = SanctumScanUserIdentityCheck::query()
            ->with('user:id,username,email')
            ->orderByDesc('id');

        if (! empty($validated['user_id'])) {
            $query->where('user_id', (int) $validated['user_id']);
        }

        $page = $query->paginate(50);

        return response()->json([
            'data' => [
                'checks' => $page->getCollection()->map(fn (SanctumScanUserIdentityCheck $check) => $this->present($check))->values(),
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ],
        ]);
    }

    public function store(
        Request $request,
        int $userId,
        UserIdentityCheckManager $manager
    ): JsonResponse {
        $admin = $request->user('admin');
        abort_unless($admin && $admin->status, 403);

        $validated = $request->validate([
            'method' => 'required|in:national_id_review,passport_review,utility_bill_review,in_person_verification,third_party_kyc,other',
            // This is an internal case/reference code only, never a government ID number.
            'reference' => 'nullable|string|max:120',
            'review_status' => 'required|in:verified,rejected,unable_to_verify',
            'summary' => 'required|string|min:20|max:2000',
        ]);

        $check = $manager->record(
            $userId,
            (int) $admin->getKey(),
            $validated['method'],
            $validated['reference'] ?? null,
            $validated['review_status'],
            $validated['summary']
        );

        if (is_string($check)) {
            return response()->json([
                'error' => [
                    'code' => $check,
                    'message' => 'The identity review could not be recorded.',
                ],
            ], 422);
        }

        return response()->json([
            'data' => [
                'check' => $this->present($check->load('user:id,username,email')),
            ],
        ], 201);
    }

    public function showGate(): JsonResponse
    {
        $settings = BasicSettings::query()->first();

        if (! $settings) {
            return response()->json([
                'error' => [
                    'code' => 'basic_settings_unavailable',
                    'message' => 'The platform settings record is not available.',
                ],
            ], 409);
        }

        return response()->json([
            'data' => [
                'buyer_identity_gate_enabled' => (bool) $settings->sanctum_exchange_identity_gate,
            ],
        ]);
    }

    public function updateGate(Request $request): JsonResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin && $admin->status, 403);

        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $settings = DB::transaction(function () use ($validated) {
            $settings = BasicSettings::query()
                ->lockForUpdate()
                ->first();

            if (! $settings) {
                return null;
            }

            $settings->forceFill([
                'sanctum_exchange_identity_gate' => (bool) $validated['enabled'],
            ])->save();

            return $settings->fresh();
        });

        if (! $settings) {
            return response()->json([
                'error' => [
                    'code' => 'basic_settings_unavailable',
                    'message' => 'The platform settings record is not available.',
                ],
            ], 409);
        }

        Log::notice('sanctum_scan.buyer_identity_gate_updated', [
            'admin_id' => (int) $admin->getKey(),
            'enabled' => (bool) $settings->sanctum_exchange_identity_gate,
        ]);

        return response()->json([
            'data' => [
                'buyer_identity_gate_enabled' => (bool) $settings->sanctum_exchange_identity_gate,
            ],
        ]);
    }

    private function present(SanctumScanUserIdentityCheck $check): array
    {
        return [
            'id' => $check->getKey(),
            'user_id' => $check->user_id,
            'username' => $check->user?->username,
            'method' => $check->method,
            'reference' => $check->reference,
            'review_status' => $check->review_status,
            'summary' => $check->summary,
            'reviewed_by_admin_id' => $check->reviewed_by_admin_id,
            'reviewed_at' => $check->reviewed_at?->toIso8601String(),
            'created_at' => $check->created_at?->toIso8601String(),
        ];
    }
}
