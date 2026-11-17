<?php

declare(strict_types=1);

/**
 * ADR 0016 G7 — dependency advisory gate.
 *
 * Fails on any security advisory affecting production dependencies unless the
 * advisory is listed below with a reviewed rationale. Allowlist entries are
 * asserted to still be reported, so a fixed advisory cannot leave a stale
 * exemption behind.
 */
$allowlist = [
    'PKSA-h7cz-q7zq-mdkt' => 'phpseclib 4.0.1 unbounded PBKDF iteration count. laravel/passport v13.11.0 pins phpseclib exactly to 4.0.1, so no fixed version is installable without replacing Passport. QRPay does not parse attacker-supplied encrypted keys, PFX, or CMS payloads. Review when Passport publishes a release that permits phpseclib >= 4.0.3 (risk R-28).',
];

$composer = getenv('COMPOSER_BINARY') ?: trim((string) shell_exec('command -v composer'));

if ($composer === '') {
    fwrite(STDERR, "G7 dependency gate: composer binary not found (set COMPOSER_BINARY).\n");
    exit(1);
}

$root = dirname(__DIR__);
chdir($root);

$command = escapeshellarg($composer).' audit --locked --no-dev --format=json 2>/dev/null';
$output = shell_exec($command);

$report = json_decode((string) $output, true);

if (! is_array($report)) {
    fwrite(STDERR, "G7 dependency gate: composer audit did not return JSON.\n");
    exit(1);
}

$advisories = $report['advisories'] ?? [];
$reportedIds = [];
$blocking = [];

foreach ($advisories as $package => $packageAdvisories) {
    foreach ($packageAdvisories as $advisory) {
        $id = (string) ($advisory['advisoryId'] ?? 'unknown');
        $reportedIds[$id] = true;

        if (isset($allowlist[$id])) {
            echo "G7 allowlisted advisory {$id} ({$package}): {$allowlist[$id]}\n";

            continue;
        }

        $blocking[] = sprintf(
            '%s %s [%s]: %s (%s)',
            $package,
            (string) ($advisory['affectedVersions'] ?? ''),
            $id,
            (string) ($advisory['title'] ?? 'no title'),
            (string) ($advisory['link'] ?? 'no link')
        );
    }
}

$staleAllowlist = array_diff(array_keys($allowlist), array_keys($reportedIds));

if ($blocking !== []) {
    fwrite(STDERR, "G7 dependency gate failed:\n");

    foreach ($blocking as $line) {
        fwrite(STDERR, "  {$line}\n");
    }

    exit(1);
}

if ($staleAllowlist !== []) {
    fwrite(STDERR, 'G7 dependency gate: allowlist entries no longer reported, remove them: '.implode(', ', $staleAllowlist)."\n");
    exit(1);
}

$abandoned = array_keys($report['abandoned'] ?? []);

if ($abandoned !== []) {
    echo 'G7 warning: abandoned packages (tracked, non-blocking): '.implode(', ', $abandoned)."\n";
}

echo "G7 dependency gate: no unallowlisted production advisories.\n";
