<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\SanctumScanExchange;
use App\Models\SanctumScanExchangePaymentEvidence;
use App\Models\Merchants\Merchant;
use App\Models\SanctumScanResource;
use App\Models\User;
use App\Services\SanctumScan\ExchangeEventRecorder;
use App\Services\SanctumScan\UserIdentityCheckManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SanctumScanExchangeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $buyer = $request->user('api');

        $exchanges = SanctumScanExchange::query()
            ->with(['merchant:id,username,business_name', 'paymentEvidence', 'events'])
            ->where('buyer_user_id', $buyer->getKey())
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

    public function accept(
        Request $request,
        int $exchangeId,
        ExchangeEventRecorder $events,
        UserIdentityCheckManager $identityChecks
    ): JsonResponse {
        $buyer = $request->user('api');

        $exchange = DB::transaction(function () use ($buyer, $exchangeId, $events, $identityChecks) {
            $exchange = SanctumScanExchange::query()
                ->whereKey($exchangeId)
                ->where('buyer_user_id', $buyer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->expireIfNeeded($exchange, $events)) {
                return 'expired';
            }

            if ($exchange->status !== 'offered') {
                return null;
            }

            if ($identityChecks->buyerIsBlocked($buyer)) {
                return 'buyer_identity_unverified';
            }

            $merchantIsActive = Merchant::query()
                ->whereKey($exchange->merchant_id)
                ->where('status', true)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->exists();

            $resourceIsPublished = SanctumScanResource::query()
                ->whereKey($exchange->resource_id)
                ->where('merchant_id', $exchange->merchant_id)
                ->where('status', 'published')
                ->lockForUpdate()
                ->exists();

            if (! $merchantIsActive || ! $resourceIsPublished) {
                $exchange->forceFill([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ])->save();

                $events->record(
                    $exchange,
                    'offer_withdrawn',
                    'The offer was withdrawn because the merchant or resource is no longer active.',
                    'system',
                    0,
                    'system_recorded'
                );

                return 'withdrawn';
            }

            $exchange->forceFill([
                'status' => 'accepted',
                'accepted_at' => now(),
            ])->save();

            $events->record(
                $exchange,
                'offer_accepted',
                'The buyer accepted the merchant offer.',
                'api',
                (int) $buyer->getKey(),
                'buyer_declared'
            );

            return $exchange->fresh(['merchant:id,username,business_name', 'paymentEvidence', 'events']);
        });

        if ($exchange === 'expired') {
            return $this->stateError('offer_expired', 'This offer has expired.');
        }

        if ($exchange === 'buyer_identity_unverified') {
            return response()->json([
                'error' => [
                    'code' => 'buyer_identity_unverified',
                    'message' => 'Your account must complete the required identity review before you can accept this offer.',
                ],
            ], 403);
        }

        if ($exchange === 'withdrawn') {
            return $this->stateError('offer_withdrawn', 'The merchant or resource is no longer active, so this offer cannot be accepted.');
        }

        if (! $exchange) {
            return $this->stateError('invalid_exchange_state', 'This offer is no longer awaiting buyer acceptance.');
        }

        return response()->json(['data' => ['exchange' => $this->present($exchange)]]);
    }

    public function decline(
        Request $request,
        int $exchangeId,
        ExchangeEventRecorder $events
    ): JsonResponse {
        $buyer = $request->user('api');

        $exchange = DB::transaction(function () use ($buyer, $exchangeId, $events) {
            $exchange = SanctumScanExchange::query()
                ->whereKey($exchangeId)
                ->where('buyer_user_id', $buyer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->expireIfNeeded($exchange, $events)) {
                return 'expired';
            }

            if ($exchange->status !== 'offered') {
                return null;
            }

            $exchange->forceFill([
                'status' => 'declined',
                'declined_at' => now(),
            ])->save();

            $events->record(
                $exchange,
                'offer_declined',
                'The buyer declined the merchant offer.',
                'api',
                (int) $buyer->getKey(),
                'buyer_declared'
            );

            return $exchange->fresh(['merchant:id,username,business_name', 'paymentEvidence', 'events']);
        });

        if ($exchange === 'expired') {
            return $this->stateError('offer_expired', 'This offer has expired.');
        }

        if (! $exchange) {
            return $this->stateError('invalid_exchange_state', 'This offer is no longer awaiting buyer acceptance.');
        }

        return response()->json(['data' => ['exchange' => $this->present($exchange)]]);
    }

    public function confirmReceipt(
        Request $request,
        int $exchangeId,
        ExchangeEventRecorder $events
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'receipt_note' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => ['code' => 'validation_failed', 'messages' => $validator->errors()->all()],
            ], 422);
        }

        $buyer = $request->user('api');

        $exchange = DB::transaction(function () use ($buyer, $exchangeId, $request, $events) {
            $exchange = SanctumScanExchange::query()
                ->whereKey($exchangeId)
                ->where('buyer_user_id', $buyer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($exchange->status !== 'dispatched') {
                return null;
            }

            $exchange->forceFill([
                'status' => 'received',
                'received_at' => now(),
            ])->save();

            $events->record(
                $exchange,
                'receipt_confirmed',
                'The buyer declared that the agreed goods or service were received.',
                'api',
                (int) $buyer->getKey(),
                'buyer_declared',
                ['note' => $request->input('receipt_note')]
            );

            return $exchange->fresh(['merchant:id,username,business_name', 'paymentEvidence', 'events']);
        });

        if (! $exchange) {
            return $this->stateError('invalid_exchange_state', 'Receipt can only be confirmed after merchant dispatch.');
        }

        return response()->json(['data' => ['exchange' => $this->present($exchange)]]);
    }

    public function submitPaymentReference(
        Request $request,
        int $exchangeId,
        ExchangeEventRecorder $events
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'rail' => 'required|in:mpesa,bank_transfer,cash,card,other',
            'reference' => 'required|string|max:120|not_regex:/\A\s*\z/',
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/\A\d{1,20}(?:\.\d{1,8})?\z/'],
            'currency' => ['required', 'string', 'size:3', 'regex:/\A[A-Z]{3}\z/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => ['code' => 'validation_failed', 'messages' => $validator->errors()->all()],
            ], 422);
        }

        $buyer = $request->user('api');
        $validated = $validator->validated();

        $result = DB::transaction(function () use ($buyer, $exchangeId, $validated, $events) {
            $exchange = SanctumScanExchange::query()
                ->whereKey($exchangeId)
                ->where('buyer_user_id', $buyer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($exchange->status, ['accepted', 'dispatched', 'received'], true)) {
                return 'invalid_state';
            }

            if (SanctumScanExchangePaymentEvidence::query()->where('exchange_id', $exchange->getKey())->exists()) {
                return 'already_submitted';
            }

            if (
                $this->normalizeDecimal((string) $validated['amount']) !==
                    $this->normalizeDecimal((string) $exchange->amount)
                || $validated['currency'] !== $exchange->currency
            ) {
                return 'amount_mismatch';
            }

            $evidence = SanctumScanExchangePaymentEvidence::create([
                'exchange_id' => $exchange->getKey(),
                'rail' => $validated['rail'],
                'reference' => trim($validated['reference']),
                'amount' => (string) $validated['amount'],
                'currency' => $validated['currency'],
                'verification_status' => 'unverified',
                'submitted_by_guard' => 'api',
                'submitted_by_id' => $buyer->getKey(),
                'submitted_at' => now(),
            ]);

            $events->record(
                $exchange,
                'payment_reference_submitted',
                'The buyer submitted a payment reference. Settlement has not been verified.',
                'api',
                (int) $buyer->getKey(),
                'unverified',
                [
                    'rail' => $evidence->rail,
                    'reference' => $evidence->reference,
                    'amount' => (string) $evidence->amount,
                    'currency' => $evidence->currency,
                ]
            );

            return $exchange->fresh(['merchant:id,username,business_name', 'paymentEvidence', 'events']);
        });

        if ($result === 'invalid_state') {
            return $this->stateError('invalid_exchange_state', 'Payment evidence can only be attached after an offer has been accepted.');
        }
        if ($result === 'already_submitted') {
            return $this->stateError('payment_reference_exists', 'A payment reference has already been submitted for this exchange.');
        }
        if ($result === 'amount_mismatch') {
            return $this->stateError('payment_amount_mismatch', 'The submitted amount and currency must match the accepted offer.');
        }

        return response()->json([
            'data' => [
                'exchange' => $this->present($result),
                'payment_evidence' => [
                    'verification_status' => 'unverified',
                    'message' => 'Reference recorded. This is not confirmation of payment or settlement.',
                ],
            ],
        ], 201);
    }

    private function expireIfNeeded(
        SanctumScanExchange $exchange,
        ExchangeEventRecorder $events
    ): bool {
        if (
            $exchange->status !== 'offered'
            || $exchange->expires_at === null
            || $exchange->expires_at->isFuture()
        ) {
            return false;
        }

        $exchange->forceFill(['status' => 'expired'])->save();

        $events->record(
            $exchange,
            'offer_expired',
            'The offer expired before the buyer accepted it.',
            'system',
            0,
            'system_recorded'
        );

        return true;
    }

    private function normalizeDecimal(string $value): ?string
    {
        if (! preg_match('/\A\d+(?:\.\d{1,8})?\z/', $value)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = rtrim($fraction, '0');

        return $fraction === '' ? $whole : $whole . '.' . $fraction;
    }

    private function stateError(string $code, string $message): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], 409);
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
        $merchant = $exchange->merchant;

        return [
            'id' => $exchange->getKey(),
            'resource' => [
                'type' => $exchange->resource_type,
                'name' => $exchange->resource_name_snapshot,
                'reference_code' => $exchange->resource_reference_snapshot,
            ],
            'merchant' => [
                'username' => $merchant?->username,
                'business_name' => $merchant?->business_name,
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
