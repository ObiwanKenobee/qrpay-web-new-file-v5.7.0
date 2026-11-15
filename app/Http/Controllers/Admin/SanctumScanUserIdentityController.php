<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SanctumScan\UserIdentityCheckManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SanctumScanUserIdentityController extends Controller
{
    public function index(int $userId, UserIdentityCheckManager $manager): JsonResponse
    {
        $user = User::query()->whereKey($userId)->whereNull('deleted_at')->firstOrFail();

        return response()->json([
            'data' => [
                'user' => [
                    'id'                           => $user->getKey(),
                    'username'                     => $user->username,
                    'identity_verified_at'         => $user->identity_verified_at?->toIso8601String(),
                    'identity_verification_method' => $user->identity_verification_method,
                ],
                'checks' => $manager->checksForUser($userId)->map(fn ($c) => [
                    'id'             => $c->getKey(),
                    'method'         => $c->method,
                    'reference'      => $c->reference,
                    'review_status'  => $c->review_status,
                    'summary'        => $c->summary,
                    'reviewed_at'    => $c->reviewed_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function store(int $userId, Request $request, UserIdentityCheckManager $manager): JsonResponse
    {
        $validated = $request->validate([
            'method'    => ['required', 'string', 'in:' . implode(',', UserIdentityCheckManager::METHODS)],
            'reference' => ['nullable', 'string', 'max:255'],
            'status'    => ['required', 'string', 'in:' . implode(',', UserIdentityCheckManager::STATUSES)],
            'summary'   => ['required', 'string', 'max:1000'],
        ]);

        $result = $manager->record(
            $userId,
            $this->adminId($request),
            $validated['method'],
            $validated['reference'] ?? null,
            $validated['status'],
            $validated['summary']
        );

        if (is_string($result)) {
            return response()->json(['error' => ['code' => $result]], 422);
        }

        return response()->json([
            'data' => [
                'check' => [
                    'id'            => $result->getKey(),
                    'method'        => $result->method,
                    'reference'     => $result->reference,
                    'review_status' => $result->review_status,
                    'summary'       => $result->summary,
                    'reviewed_at'   => $result->reviewed_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    private function adminId(Request $request): int
    {
        return (int) $request->user('admin')?->getKey();
    }
}
