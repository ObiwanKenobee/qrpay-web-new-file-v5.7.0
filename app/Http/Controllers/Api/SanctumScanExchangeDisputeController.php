<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SanctumScanExchangeDispute;
use App\Services\SanctumScan\ExchangeDisputeManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SanctumScanExchangeDisputeController extends Controller
{
    public function show(
        Request $request,
        int $exchangeId,
        ExchangeDisputeManager $disputes
    ): JsonResponse {
        [$guard, $actorId] = $this->actor($request);
        $dispute = $disputes->show($exchangeId, $guard, $actorId);

        return response()->json([
            'data' => [
                'dispute' => $dispute ? $this->present($dispute) : null,
            ],
        ]);
    }

    public function open(
        Request $request,
        int $exchangeId,
        ExchangeDisputeManager $disputes
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'category' => 'required|in:non_delivery,quantity_mismatch,quality_concern,payment_question,terms_disagreement,other',
            'statement' => ['required', 'string', 'max:3000', 'not_regex:/\A\s*\z/'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        [$guard, $actorId] = $this->actor($request);
        $result = $disputes->open(
            $exchangeId,
            $guard,
            $actorId,
            $validator->validated()['category'],
            $validator->validated()['statement']
        );

        return $this->result($result, 201);
    }

    public function addMessage(
        Request $request,
        int $exchangeId,
        ExchangeDisputeManager $disputes
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'message' => ['required', 'string', 'max:3000', 'not_regex:/\A\s*\z/'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        [$guard, $actorId] = $this->actor($request);
        $result = $disputes->addMessage(
            $exchangeId,
            $guard,
            $actorId,
            $validator->validated()['message']
        );

        return $this->result($result);
    }

    public function proposeResolution(
        Request $request,
        int $exchangeId,
        ExchangeDisputeManager $disputes
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'summary' => ['required', 'string', 'max:2000', 'not_regex:/\A\s*\z/'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        [$guard, $actorId] = $this->actor($request);
        $result = $disputes->proposeResolution(
            $exchangeId,
            $guard,
            $actorId,
            $validator->validated()['summary']
        );

        return $this->result($result);
    }

    public function acceptResolution(
        Request $request,
        int $exchangeId,
        ExchangeDisputeManager $disputes
    ): JsonResponse {
        [$guard, $actorId] = $this->actor($request);
        $result = $disputes->acceptResolution($exchangeId, $guard, $actorId);

        return $this->result($result);
    }

    public function rejectResolution(
        Request $request,
        int $exchangeId,
        ExchangeDisputeManager $disputes
    ): JsonResponse {
        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'max:2000', 'not_regex:/\A\s*\z/'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        [$guard, $actorId] = $this->actor($request);
        $result = $disputes->rejectResolution(
            $exchangeId,
            $guard,
            $actorId,
            $validator->validated()['reason']
        );

        return $this->result($result);
    }

    public function withdraw(
        Request $request,
        int $exchangeId,
        ExchangeDisputeManager $disputes
    ): JsonResponse {
        [$guard, $actorId] = $this->actor($request);
        $result = $disputes->withdraw($exchangeId, $guard, $actorId);

        return $this->result($result);
    }

    private function actor(Request $request): array
    {
        $merchant = $request->user('merchant_api');

        if ($merchant) {
            return ['merchant_api', (int) $merchant->getKey()];
        }

        $buyer = $request->user('api');

        abort_unless($buyer, 401);

        return ['api', (int) $buyer->getKey()];
    }

    private function result(SanctumScanExchangeDispute|string $result, int $successStatus = 200): JsonResponse
    {
        if (is_string($result)) {
            $messages = [
                'exchange_not_disputable' => 'A dispute can only be opened after the offer has been accepted.',
                'dispute_exists' => 'A dispute record already exists for this exchange.',
                'invalid_dispute_state' => 'This action is not available in the current dispute state.',
                'proposer_cannot_accept' => 'The participant who proposed a resolution cannot accept their own proposal.',
                'proposer_cannot_reject_own_resolution' => 'The participant who proposed a resolution cannot reject their own proposal.',
                'withdrawal_not_allowed' => 'Only the initiating participant may withdraw an open dispute before the other participant responds.',
            ];

            return response()->json([
                'error' => [
                    'code' => $result,
                    'message' => $messages[$result] ?? 'The dispute action could not be completed.',
                ],
            ], 409);
        }

        return response()->json([
            'data' => [
                'dispute' => $this->present($result),
            ],
        ], $successStatus);
    }

    private function validationError($validator): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'validation_failed',
                'messages' => $validator->errors()->all(),
            ],
        ], 422);
    }

    private function present(SanctumScanExchangeDispute $dispute): array
    {
        $role = fn (?string $guard) => $guard === null
            ? null
            : ($guard === 'merchant_api' ? 'merchant' : 'buyer');

        return [
            'id' => $dispute->getKey(),
            'exchange_id' => $dispute->exchange_id,
            'category' => $dispute->category,
            'status' => $dispute->status,
            'opened_by' => $role($dispute->opened_by_guard),
            'opened_at' => $dispute->opened_at?->toIso8601String(),
            'resolution_summary' => $dispute->resolution_summary,
            'resolution_proposed_by' => $role($dispute->resolution_proposed_by_guard),
            'resolution_proposed_at' => $dispute->resolution_proposed_at?->toIso8601String(),
            'resolved_by' => $role($dispute->resolved_by_guard),
            'resolved_at' => $dispute->resolved_at?->toIso8601String(),
            'withdrawn_at' => $dispute->withdrawn_at?->toIso8601String(),
            'messages' => $dispute->messages->map(fn ($message) => [
                'id' => $message->getKey(),
                'type' => $message->message_type,
                'body' => $message->body,
                'author' => $role($message->recorded_by_guard),
                'recorded_at' => $message->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
