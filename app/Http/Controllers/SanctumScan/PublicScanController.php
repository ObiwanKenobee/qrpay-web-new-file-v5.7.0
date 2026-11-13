<?php

namespace App\Http\Controllers\SanctumScan;

use App\Http\Controllers\Controller;
use App\Services\SanctumScan\ScanReferenceResolver;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicScanController extends Controller
{
    public function show(string $reference, ScanReferenceResolver $resolver): Response|View
    {
        $document = $resolver->resolve($reference);

        if ($document === null) {
            return response()->view('sanctum-scan.unavailable', [], 404);
        }

        return response()->view('sanctum-scan.show', [
            'scan' => $document['data'],
            'pageTitle' => $document['data']['resource']['display_name'] ?? 'Sanctum Scan',
        ]);
    }
}
