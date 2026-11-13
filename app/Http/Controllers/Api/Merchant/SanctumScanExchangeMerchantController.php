<?php

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Merchants\Merchant;
use App\Models\SanctumScanExchange;
use App\Models\SanctumScanExchangePaymentEvidence;
use App\Models\SanctumScanResource;
use App\Models\User;
use App\Services\SanctumScan\ExchangeEventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SanctumScanExchangeMerchantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $merchant = $request->user('merchant_api');

        $exchanges = SanctumScanExchange::query()
            ->with(['buyer:id,username', 'resource:id,display_name', 'paymentEvidence', 'events'])
            ->where('merchant_id', $merchant->getKey())
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (SanctumScanExchange $exchange) => $this->present($exchange));

        return response()->json([
            'data' => [
                'exchanges' => $exchanges,
            ],
        ]);
    }

    public function store(
        Request $request,
        ExchangeEventRecorder $events
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'resource_id' => 'required|integer|min:1',
            'buyer_username' => 'required|string|max:80',
            'quantity' => 'required|numeric|gt:0|max:999999999999.9999',
            'unit' => 'nullable|string|max:24',
            'amount' => 'required|numeric|gt:0',
            'currency' => ['required', 'string', 'size:3', 'regex:/\A[A-Z]{3}\z/'],
            'terms' => 'nullable|string|max:2000',
            'expires_in_hours' => 'nullable|integer|min:1|max:168',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'validation_failed',
                    'messages' => $validator->errors()->all(),
                ],
            ], 422);
        }

        $merchant = $request->user('merchant_api');
        $validated = $validator->validated();

        $exchange = DB::transaction(function () use ($merchant, $validated, $events) {
            $resource = SanctumScanResource::query()
                ->whereKey($validated['resource_id'])
                ->where('merchant_id', $merchant->getKey())
                ->where('status', 'published')
                ->lockForUpdate()
                ->first();

            if (! $resource) {
                return null;
            }

            $buyer = User::query()
                ->where('username', $validated['buyer_username'])
                ->where('status', true)
                ->whereNull('deleted_at')
                ->first();

            if (! $buyer) {
                return false;
            }

            $unit = trim((string) ($validated['unit'] ?? $resource->unit));

            if ($unit === '') {
                return ['error' => 'A unit is required for this offer.'];
            }

            if ($resource->unit && $validated['unit'] ?? false) {
                if (strcasecmp($resource->unit, $unit) !== 0) {
                    return ['error' => 'The offer unit must match the registered resource unit.'];
                }
            }

            $expiryHours = (int) ($validated['expires_in_hours'] ?? 72);
            $amount = (string) $validated['amount'];

            $exchange = SanctumScanExchange::create([
                'merchant_id' => $merchant->getKey(),
                'buyer_user_id' => $buyer->getKey(),
                'resource_id' => $resource->getKey(),
                'resource_type' => $resource->resource_type,
                'resource_name_snapshot' => $resource->display_name,
                'resource_reference_snapshot' => $resource->reference_code,
                'quantity' => $validated['quantity'],
                'unit' => $unit,
                'amount' => $amount,
                'currency' => $validated['currency'],
                'terms' => $validated['terms'] ?? null,
                'status' => 'offered',
                'expires_at' => now()->addHours($expiryHours),
            ]);

            $events->record(
                $exchange,
                'offer_issued',
                'The merchant issued an economic offer to the named buyer.',
                'merchant_api',
                (int) $merchant->getKey(),
                'merchant_declared',
                [
                    'resource_type' => $resource->resource_type,
                    'resource_name' => $resource->display_name,
                    'quantity' => (string) $exchange->quantity,
                    'unit' => $exchange->unit,
                    'amount' => (string) $exchange->amount,
                    'currency' => $exchange->currency,
                    'expires_at' => $exchange->expires_at?->toIso8601String(),
                ]
            );

            return $exchange;
        });

        if ($exchange === null) {
            return response()->json([
                'error' => [
                    'code' => 'resource_unavailable',
                    'message' => 'The selected resource is not published or does not belong to this merchant.',
                ],
            ], 404);
        }

        if ($exchange === false) {
            return response()->json([
                'error' => [
                    'code' => 'buyer_unavailable',
                    'message' => 'The buyer account could not be found or is not active.',
                ],
            ], 422);
        }

        if (is_array($exchange) && isset($exchange['error'])) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_offer_unit',
                    'message' => $exchange['error'],
                ],
            ], 422);
        }

        $exchange->load(['buyer:id,username', 'resource:id,display_name', 'paymentEvidence', 'events']);

        return response()->json([
            'data' => [
                'exchange' => $this->present($exchange),
            ],
        ], 201);
    }

    public function dispatch(
        Request $request,
        int $exchangeId,
        ExchangeEventRecorder $events
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'dispatch_reference' => 'nullable|string|max:120',
            'dispatch_note' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => ['code' => 'validation_failed', 'messages' => $validator->errors()->all()],
            ], 422);
        }

        $merchant = $request->user('merchant_api');

        $exchange = DB::transaction(function () use ($merchant, $exchangeId, $request, $events) {
            $exchange = SanctumScanExchange::query()
                ->whereKey($exchangeId)
                ->where('merchant_id', $merchant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($exchange->status !== 'accepted') {
                return null;
            }

            $exchange->forceFill([
                'status' => 'dispatched',
                'dispatched_at' => now(),
            ])->save();

            $events->record(
                $exchange,
                'dispatch_declared',
                'The merchant declared that the agreed goods or service have been dispatched.',
                'merchant_api',
                (int) $merchant->getKey(),
                'merchant_declared',
                [
                    'dispatch_reference' => $request->input('dispatch_reference'),
                    'note' => $request->input('dispatch_note'),
                ]
            );

            return $exchange->fresh(['buyer:id,username', 'paymentEvidence', 'events']);
        });

        if (! $exchange) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_exchange_state',
                    'message' => 'Only an accepted exchange can be marked as dispatched.',
                ],
            ], 409);
        }

        return response()->json(['data' => ['exchange' => $this->present($exchange)]]);
    }

    public function cancel(
        Request $request,
        int $exchangeId,
        ExchangeEventRecorder $events
    ): JsonResponse {
        $merchant = $request->user('merchant_api');

        $exchange = DB::transaction(function () use ($merchant, $exchangeId, $events) {
            $exchange = SanctumScanExchange::query()
                ->whereKey($exchangeId)
                ->where('merchant_id', $merchant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($exchange->status !== 'offered') {
                return null;
            }

            $exchange->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ])->save();

            $events->record(
                $exchange,
                'offer_cancelled',
                'The merchant withdrew the offer before buyer acceptance.',
                'merchant_api',
                (int) $merchant->getKey(),
                'merchant_declared'
            );

            return $exchange->fresh(['buyer:id,username', 'paymentEvidence', 'events']);
        });

        if (! $exchange) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_exchange_state',
                    'message' => 'Only an offer awaiting buyer acceptance can be cancelled by the merchant.',
                ],
            ], 409);
        }

        return response()->json(['data' => ['exchange' => $this->present($exchange)]]);
    }

    private function present(SanctumScanExchange $exchange): array
    {
        $status = $exchange->status;

        if (
            $status === 'offered'
            && $exchange->expires_at !== null
            && $exchange->expires_at->isPast()
        ) {
            $status = 'expired';
        }

        $evidence = $exchange->paymentEvidence;

        return [
            'id' => $exchange->getKey(),
            'resource' => [
                'type' => $exchange->resource_type,
                'name' => $exchange->resource_name_snapshot,
                'reference_code' => $exchange->resource_reference_snapshot,
            ],
            'buyer' => [
                'username' => $exchange->buyer?->username,
            ],
            'quantity' => (string) $exchange->quantity,
            'unit' => $exchange->unit,
            'amount' => (string) $exchange->amount,
            'currency' => $exchange->currency,
            'terms' => $exchange->terms,
            'status' => $status,
            'expires_at' => $exchange->expires_at?->toIso8601String(),
            'accepted_at' => $exchange->accepted_at?->toIso8601String(),
            'dispatched_at' => $exchange->dispatched_at?->toIso8601String(),
            'received_at' => $exchange->received_at?->toIso8601String(),
            'payment_evidence' => $evidence ? [
                'rail' => $evidence->rail,
                'reference' => $evidence->reference,
                'amount' => (string) $evidence->amount,
                'currency' => $evidence->currency,
                'verification_status' => $evidence->verification_status,
                'submitted_at' => $evidence->submitted_at?->toIso8601String(),
            ] : null,
            'events' => $exchange->events->map(fn ($event) => [
                'type' => $event->event_type,
                'description' => $event->description,
                'verification_status' => $event->verification_status,
                'event_data' => $event->event_data,
                'recorded_at' => $event->created_at?->toIso8601String(),
            ])->values()->all(),
            'created_at' => $exchange->created_at?->toIso8601String(),
        ];
    }
}
