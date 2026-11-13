<?php

namespace App\Services\SanctumScan;

use App\Models\SanctumScanExchange;
use App\Models\SanctumScanExchangeDispute;
use App\Models\SanctumScanExchangeDisputeEvidence;
use App\Models\SanctumScanExchangeEvidenceReview;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DisputeEvidenceManager
{
    public function __construct(
        private readonly ExchangeEventRecorder $events
    ) {
    }

    public function listForParticipant(
        int $exchangeId,
        string $actorGuard,
        int $actorId
    ) {
        $exchange = $this->participantExchange($exchangeId, $actorGuard, $actorId);
        $dispute = SanctumScanExchangeDispute::query()
            ->where('exchange_id', $exchange->getKey())
            ->firstOrFail();

        return SanctumScanExchangeDisputeEvidence::query()
            ->with(['review', 'attestations.institution'])
            ->where('dispute_id', $dispute->getKey())
            ->orderBy('id')
            ->get();
    }

    public function attach(
        int $exchangeId,
        string $actorGuard,
        int $actorId,
        UploadedFile $file,
        string $evidenceType,
        ?string $caption
    ): SanctumScanExchangeDisputeEvidence|string {
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $extensions = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];

        if (! isset($extensions[$mimeType])) {
            return 'unsupported_file_type';
        }

        $sha256 = hash_file('sha256', $file->getRealPath());

        if (! is_string($sha256)) {
            return 'file_unreadable';
        }

        $storagePath = null;

        try {
            return DB::transaction(function () use (
                $exchangeId,
                $actorGuard,
                $actorId,
                $file,
                $evidenceType,
                $caption,
                $mimeType,
                $extensions,
                $sha256,
                &$storagePath
            ) {
                $exchange = $this->participantExchange($exchangeId, $actorGuard, $actorId, true);
                $dispute = SanctumScanExchangeDispute::query()
                    ->where('exchange_id', $exchange->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($dispute->status !== 'open') {
                    return 'dispute_not_open';
                }

                $duplicate = SanctumScanExchangeDisputeEvidence::query()
                    ->where('dispute_id', $dispute->getKey())
                    ->where('sha256', $sha256)
                    ->exists();

                if ($duplicate) {
                    return 'duplicate_evidence';
                }

                $directory = 'sanctum-scan/disputes/' . $dispute->getKey();
                $filename = (string) Str::uuid() . '.' . $extensions[$mimeType];
                $storagePath = $file->storeAs($directory, $filename, 'local');

                if (! is_string($storagePath) || $storagePath === '') {
                    $storagePath = null;

                    return 'storage_failed';
                }

                $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
                $originalName = preg_replace('/[\r\n\x00-\x1F\x7F]/', '', $originalName) ?: 'evidence.' . $extensions[$mimeType];
                $originalName = mb_substr($originalName, 0, 180);

                $evidence = SanctumScanExchangeDisputeEvidence::create([
                    'dispute_id' => $dispute->getKey(),
                    'evidence_type' => $evidenceType,
                    'original_name' => $originalName,
                    'storage_disk' => 'local',
                    'storage_path' => $storagePath,
                    'mime_type' => $mimeType,
                    'size_bytes' => (int) $file->getSize(),
                    'sha256' => $sha256,
                    'caption' => $caption,
                    'submitted_by_guard' => $actorGuard,
                    'submitted_by_id' => $actorId,
                    'submitted_at' => now(),
                ]);

                $this->events->record(
                    $exchange,
                    'dispute_evidence_submitted',
                    'A participant submitted a private evidence file to the dispute record.',
                    $actorGuard,
                    $actorId,
                    $actorGuard === 'merchant_api' ? 'merchant_declared' : 'buyer_declared',
                    [
                        'dispute_id' => $dispute->getKey(),
                        'evidence_id' => $evidence->getKey(),
                        'evidence_type' => $evidenceType,
                        'sha256' => $sha256,
                        'size_bytes' => (int) $file->getSize(),
                    ]
                );

                return $evidence->fresh(['review', 'attestations.institution']);
            });
        } catch (Throwable $exception) {
            if ($storagePath !== null) {
                Storage::disk('local')->delete($storagePath);
            }

            throw $exception;
        }
    }

    public function findForParticipant(
        int $exchangeId,
        int $evidenceId,
        string $actorGuard,
        int $actorId
    ): SanctumScanExchangeDisputeEvidence {
        $exchange = $this->participantExchange($exchangeId, $actorGuard, $actorId);
        $dispute = SanctumScanExchangeDispute::query()
            ->where('exchange_id', $exchange->getKey())
            ->firstOrFail();

        return SanctumScanExchangeDisputeEvidence::query()
            ->with(['review', 'attestations.institution'])
            ->where('dispute_id', $dispute->getKey())
            ->whereKey($evidenceId)
            ->firstOrFail();
    }

    public function findForAdmin(int $evidenceId): SanctumScanExchangeDisputeEvidence
    {
        return SanctumScanExchangeDisputeEvidence::query()
            ->with(['review', 'dispute.exchange'])
            ->whereKey($evidenceId)
            ->firstOrFail();
    }

    public function pendingForAdmin()
    {
        return SanctumScanExchangeDisputeEvidence::query()
            ->with(['dispute.exchange.merchant:id,username,business_name', 'dispute.exchange.buyer:id,username'])
            ->whereDoesntHave('review')
            ->latest('id')
            ->paginate(50);
    }

    public function review(
        int $evidenceId,
        int $adminId,
        string $reviewStatus,
        string $reviewMethod,
        string $reviewSummary
    ): SanctumScanExchangeEvidenceReview|string {
        return DB::transaction(function () use ($evidenceId, $adminId, $reviewStatus, $reviewMethod, $reviewSummary) {
            $evidence = SanctumScanExchangeDisputeEvidence::query()
                ->with('dispute.exchange')
                ->whereKey($evidenceId)
                ->lockForUpdate()
                ->firstOrFail();

            if (SanctumScanExchangeEvidenceReview::query()->where('evidence_id', $evidence->getKey())->exists()) {
                return 'evidence_already_reviewed';
            }

            if (! $this->fileIntegrityIsValid($evidence)) {
                return 'evidence_integrity_failure';
            }

            $review = SanctumScanExchangeEvidenceReview::create([
                'evidence_id' => $evidence->getKey(),
                'review_status' => $reviewStatus,
                'review_method' => $reviewMethod,
                'evidence_sha256' => $evidence->sha256,
                'review_summary' => trim($reviewSummary),
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
                'created_at' => now(),
            ]);

            $exchange = $evidence->dispute->exchange;
            $eventStatus = match ($reviewStatus) {
                'verified' => 'platform_verified',
                'rejected' => 'platform_rejected',
                default => 'platform_unable_to_verify',
            };

            $this->events->record(
                $exchange,
                'dispute_evidence_reviewed',
                'A platform administrator recorded a review decision for submitted dispute evidence.',
                'admin',
                $adminId,
                $eventStatus,
                [
                    'dispute_id' => $evidence->dispute_id,
                    'evidence_id' => $evidence->getKey(),
                    'review_status' => $reviewStatus,
                    'review_method' => $reviewMethod,
                    'evidence_sha256' => $evidence->sha256,
                ]
            );

            return $review->fresh();
        });
    }

    public function downloadForParticipant(
        int $exchangeId,
        int $evidenceId,
        string $actorGuard,
        int $actorId
    ): StreamedResponse|string {
        $evidence = $this->findForParticipant($exchangeId, $evidenceId, $actorGuard, $actorId);

        return $this->download($evidence);
    }

    public function downloadForAdmin(int $evidenceId): StreamedResponse|string
    {
        return $this->download($this->findForAdmin($evidenceId));
    }

    public function fileIntegrityIsValid(SanctumScanExchangeDisputeEvidence $evidence): bool
    {
        if ($evidence->storage_disk !== 'local') {
            return false;
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($evidence->storage_path)) {
            return false;
        }

        $actualHash = hash_file('sha256', $disk->path($evidence->storage_path));

        return is_string($actualHash) && hash_equals($evidence->sha256, $actualHash);
    }

    private function download(SanctumScanExchangeDisputeEvidence $evidence): StreamedResponse|string
    {
        if (! $this->fileIntegrityIsValid($evidence)) {
            return 'evidence_integrity_failure';
        }

        return Storage::disk($evidence->storage_disk)->download(
            $evidence->storage_path,
            $evidence->original_name,
            [
                'Content-Type' => $evidence->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    private function participantExchange(
        int $exchangeId,
        string $actorGuard,
        int $actorId,
        bool $lock = false
    ): SanctumScanExchange {
        $query = SanctumScanExchange::query()->whereKey($exchangeId);

        if ($actorGuard === 'merchant_api') {
            $query->where('merchant_id', $actorId);
        } elseif ($actorGuard === 'api') {
            $query->where('buyer_user_id', $actorId);
        } else {
            abort(403);
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }
}
