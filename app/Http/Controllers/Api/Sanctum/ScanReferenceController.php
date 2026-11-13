<?php

namespace App\Http\Controllers\Api\Sanctum;

use App\Http\Controllers\Controller;
use App\Services\SanctumScan\ScanReferenceResolver;
use Illuminate\Http\JsonResponse;

class ScanReferenceController extends Controller
{
    /**
     * Return a machine-readable public scan document.
     *
     * Resolution never authenticates the scanner, grants access to a private
     * resource, authorizes a payment, or confirms settlement or delivery.
     */
    public function show(string $reference, ScanReferenceResolver $resolver): JsonResponse
    {
        $document = $resolver->resolve($reference);

        if ($document === null) {
            return response()->json([
                'error' => [
                    'code' => 'scan_reference_unavailable',
                    'message' => 'This scan reference is invalid, expired, or unavailable.',
                ],
            ], 404);
        }

        return response()->json($document);
    }
}
