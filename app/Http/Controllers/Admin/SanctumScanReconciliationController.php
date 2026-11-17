<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SanctumScanExchange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SanctumScanReconciliationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin && $admin->status, 403);

        $query = DB::table('sanctum_scan_exchanges as exchanges')
            ->leftJoin('sanctum_scan_exchange_disputes as disputes', 'disputes.exchange_id', '=', 'exchanges.id')
            ->leftJoin('merchants', 'merchants.id', '=', 'exchanges.merchant_id')
            ->leftJoin('users as buyers', 'buyers.id', '=', 'exchanges.buyer_user_id')
            ->where(function ($query) {
                $query->whereIn('exchanges.settlement_status', [
                    'awaiting_payment',
                    'payment_evidence_submitted',
                    'payment_rejected',
                ])->orWhereIn('disputes.status', ['open', 'resolution_proposed']);
            })
            ->select([
                'exchanges.id as exchange_id',
                'exchanges.status as exchange_status',
                'exchanges.settlement_status',
                'exchanges.settlement_status_updated_at',
                'exchanges.amount',
                'exchanges.currency',
                'merchants.username as merchant_username',
                'buyers.username as buyer_username',
                'disputes.id as dispute_id',
                'disputes.category as dispute_category',
                'disputes.status as dispute_status',
                'disputes.opened_at as dispute_opened_at',
            ])
            ->orderByRaw("CASE WHEN disputes.status IN ('open', 'resolution_proposed') THEN 0 ELSE 1 END")
            ->orderBy('exchanges.updated_at');

        if ($request->filled('settlement_status')) {
            $query->where('exchanges.settlement_status', $request->string('settlement_status')->toString());
        }

        if ($request->filled('dispute_status')) {
            $query->where('disputes.status', $request->string('dispute_status')->toString());
        }

        $page = $query->paginate(min(max((int) $request->query('per_page', 25), 1), 100));

        return response()->json([
            'data' => [
                'cases' => $page->items(),
                'pagination' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'per_page' => $page->perPage(),
                    'total' => $page->total(),
                ],
            ],
        ]);
    }

    public function show(Request $request, int $exchangeId): JsonResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin && $admin->status, 403);

        $exchange = SanctumScanExchange::query()
            ->with([
                'merchant:id,username,business_name',
                'buyer:id,username',
                'paymentEvidences',
                'events',
                'dispute.messages',
                'dispute.evidence.review',
            ])
            ->findOrFail($exchangeId);

        $dispute = $exchange->dispute;

        return response()->json([
            'data' => [
                'case' => [
                    'exchange' => [
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
                        'buyer' => ['username' => $exchange->buyer?->username],
                    ],
                    'payment_evidence_history' => $exchange->paymentEvidences->map(fn ($item) => [
                        'id' => $item->getKey(),
                        'version' => $item->version,
                        'supersedes_id' => $item->supersedes_id,
                        'rail' => $item->rail,
                        'reference' => $item->reference,
                        'amount' => (string) $item->amount,
                        'currency' => $item->currency,
                        'verification_status' => $item->verification_status,
                        'submitted_by_guard' => $item->submitted_by_guard,
                        'submitted_by_id' => $item->submitted_by_id,
                        'submitted_at' => $item->submitted_at?->toIso8601String(),
                    ])->values(),
                    'settlement_timeline' => $exchange->events
                        ->where('event_type', 'settlement_status_changed')
                        ->map(fn ($event) => [
                            'id' => $event->getKey(),
                            'event_data' => $event->event_data,
                            'verification_status' => $event->verification_status,
                            'recorded_by_guard' => $event->recorded_by_guard,
                            'recorded_by_id' => $event->recorded_by_id,
                            'recorded_at' => $event->created_at?->toIso8601String(),
                        ])->values(),
                    'dispute' => $dispute ? [
                        'id' => $dispute->getKey(),
                        'category' => $dispute->category,
                        'status' => $dispute->status,
                        'opened_by_guard' => $dispute->opened_by_guard,
                        'opened_by_id' => $dispute->opened_by_id,
                        'opened_at' => $dispute->opened_at?->toIso8601String(),
                        'resolution_summary' => $dispute->resolution_summary,
                        'resolution_proposed_at' => $dispute->resolution_proposed_at?->toIso8601String(),
                        'resolved_by_guard' => $dispute->resolved_by_guard,
                        'resolved_by_id' => $dispute->resolved_by_id,
                        'resolved_at' => $dispute->resolved_at?->toIso8601String(),
                        'messages' => $dispute->messages->map(fn ($message) => [
                            'id' => $message->getKey(),
                            'type' => $message->message_type,
                            'body' => $message->body,
                            'recorded_by_guard' => $message->recorded_by_guard,
                            'recorded_by_id' => $message->recorded_by_id,
                            'recorded_at' => $message->created_at?->toIso8601String(),
                        ])->values(),
                        'evidence' => $dispute->evidence->map(fn ($item) => [
                            'id' => $item->getKey(),
                            'type' => $item->evidence_type,
                            'original_name' => $item->original_name,
                            'mime_type' => $item->mime_type,
                            'size_bytes' => $item->size_bytes,
                            'sha256' => $item->sha256,
                            'caption' => $item->caption,
                            'submitted_by_guard' => $item->submitted_by_guard,
                            'submitted_by_id' => $item->submitted_by_id,
                            'submitted_at' => $item->submitted_at?->toIso8601String(),
                            'review' => $item->review ? [
                                'status' => $item->review->review_status,
                                'method' => $item->review->review_method,
                                'evidence_sha256' => $item->review->evidence_sha256,
                                'summary' => $item->review->review_summary,
                                'reviewed_by_admin_id' => $item->review->reviewed_by_admin_id,
                                'reviewed_at' => $item->review->reviewed_at?->toIso8601String(),
                            ] : null,
                        ])->values(),
                    ] : null,
                ],
            ],
        ]);
    }
}
