<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SanctumScanExchange;
use App\Services\SanctumScan\ExchangeSettlementManager;
use App\Services\SanctumScan\SettlementStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SanctumScanExchangeSettlementController extends Controller
{
    public function show(Request $request, int $exchangeId): JsonResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin && $admin->status, 403);

        $exchange = SanctumScanExchange::query()
            ->with([
                'merchant:id,username,business_name',
                'buyer:id,username',
                'paymentEvidence',
                'paymentEvidences',
                'events' => fn ($query) => $query->where('event_type', 'settlement_status_changed'),
            ])
            ->findOrFail($exchangeId);

        return response()->json([
            'data' => [
                'exchange' => $this->present($exchange),
            ],
        ]);
    }

    public function transition(
        Request $request,
        int $exchangeId,
        ExchangeSettlementManager $settlement
    ): JsonResponse {
        $admin = $request->user('admin');
        abort_unless($admin && $admin->status, 403);

        $validator = Validator::make($request->all(), [
            'to_status' => 'required|in:'.implode(',', SettlementStateMachine::STATUSES),
            'reason' => 'required|string|min:10|max:500',
            'expected_status' => 'sometimes|nullable|in:'.implode(',', SettlementStateMachine::STATUSES),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'validation_failed',
                    'messages' => $validator->errors()->all(),
                ],
            ], 422);
        }

        $validated = $validator->validated();

        $exchange = SanctumScanExchange::query()->findOrFail($exchangeId);

        $result = $settlement->transition(
            $exchange,
            $validated['to_status'],
            'admin',
            (int) $admin->getKey(),
            $validated['reason'],
            $validated['expected_status'] ?? null
        );

        if (! $result->ok) {
            return $this->transitionError($result->error);
        }

        return response()->json([
            'data' => [
                'exchange' => $this->present(
                    $result->exchange->load([
                        'merchant:id,username,business_name',
                        'buyer:id,username',
                        'paymentEvidence',
                        'paymentEvidences',
                        'events' => fn ($query) => $query->where('event_type', 'settlement_status_changed'),
                    ])
                ),
            ],
        ]);
    }

    private function transitionError(?string $code): JsonResponse
    {
        [$status, $message] = match ($code) {
            'invalid_settlement_transition' => [409, 'The requested settlement transition is not allowed from the current status.'],
            'already_in_state' => [409, 'The exchange is already in the requested settlement status.'],
            'stale_expected_status' => [409, 'The settlement status changed before this request was applied.'],
            'unknown_settlement_status' => [409, 'The exchange has an unrecognised settlement status.'],
            'invalid_actor' => [403, 'This actor cannot perform the requested settlement transition.'],
            default => [409, 'The settlement transition could not be applied.'],
        };

        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }

    private function present(SanctumScanExchange $exchange): array
    {
        $evidence = $exchange->paymentEvidence;

        return [
            'id' => $exchange->getKey(),
            'status' => $exchange->status,
            'settlement_status' => $exchange->settlement_status,
            'settlement_status_updated_at' => $exchange->settlement_status_updated_at?->toIso8601String(),
            'amount' => (string) $exchange->amount,
            'currency' => $exchange->currency,
            'quantity' => (string) $exchange->quantity,
            'unit' => $exchange->unit,
            'merchant' => [
                'username' => $exchange->merchant?->username,
                'business_name' => $exchange->merchant?->business_name,
            ],
            'buyer' => [
                'username' => $exchange->buyer?->username,
            ],
            'payment_evidence' => $evidence ? [
                'version' => $evidence->version,
                'rail' => $evidence->rail,
                'reference' => $evidence->reference,
                'amount' => (string) $evidence->amount,
                'currency' => $evidence->currency,
                'verification_status' => $evidence->verification_status,
                'submitted_at' => $evidence->submitted_at?->toIso8601String(),
            ] : null,
            'payment_evidence_history' => $exchange->paymentEvidences->map(fn ($version) => [
                'version' => $version->version,
                'supersedes_id' => $version->supersedes_id,
                'rail' => $version->rail,
                'reference' => $version->reference,
                'amount' => (string) $version->amount,
                'currency' => $version->currency,
                'submitted_at' => $version->submitted_at?->toIso8601String(),
            ])->values()->all(),
            'settlement_events' => $exchange->events->map(fn ($event) => [
                'id' => $event->getKey(),
                'from' => $event->event_data['from'] ?? null,
                'to' => $event->event_data['to'] ?? null,
                'reason' => $event->event_data['reason'] ?? null,
                'verification_status' => $event->verification_status,
                'recorded_by_guard' => $event->recorded_by_guard,
                'recorded_by_id' => $event->recorded_by_id,
                'recorded_at' => $event->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
