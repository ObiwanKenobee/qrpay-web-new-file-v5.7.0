<?php

namespace App\Console\Commands;

use App\Services\SanctumScan\ProvenanceManifestVerifier;
use Illuminate\Console\Command;

class VerifySanctumScanProvenance extends Command
{
    protected $signature = 'sanctum-scan:verify-provenance
                            {file : Path to a saved Sanctum Scan provenance JSON export}';

    protected $description = 'Verify a saved Sanctum Scan provenance manifest offline';

    public function handle(ProvenanceManifestVerifier $verifier): int
    {
        $inputPath = (string) $this->argument('file');
        $resolvedPath = realpath($inputPath);

        if ($resolvedPath === false) {
            $resolvedPath = realpath(base_path($inputPath));
        }

        if ($resolvedPath === false || ! is_file($resolvedPath) || ! is_readable($resolvedPath)) {
            $this->error('The provenance export file does not exist or is not readable.');

            return self::FAILURE;
        }

        $fileSize = filesize($resolvedPath);

        if ($fileSize === false || $fileSize > ProvenanceManifestVerifier::MAX_BYTES) {
            $this->error('The provenance export exceeds the 25 MiB verification limit.');

            return self::FAILURE;
        }

        $contents = file_get_contents($resolvedPath);

        if (! is_string($contents)) {
            $this->error('The provenance export could not be read.');

            return self::FAILURE;
        }

        $result = $verifier->verifyJson($contents);

        if (! $result['valid']) {
            $this->error('Provenance verification failed.');

            foreach ($result['errors'] as $error) {
                $this->line(' - ' . $error);
            }

            return self::FAILURE;
        }

        $this->info('Provenance manifest verified.');
        $this->line('SHA-256: ' . $result['content_sha256']);
        $this->line('Checks: ' . implode(', ', $result['checks']));

        return self::SUCCESS;
    }
}
