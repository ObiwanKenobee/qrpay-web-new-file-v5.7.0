<?php

declare(strict_types=1);

/**
 * ADR 0016 G1 — conflict-marker gate.
 *
 * Scans every git-tracked file for merge-conflict markers. A marker is never
 * intentional content, so any finding fails the build.
 */
$root = dirname(__DIR__);
chdir($root);

$tracked = shell_exec('git ls-files -z');

if (! is_string($tracked) || $tracked === '') {
    fwrite(STDERR, "Unable to enumerate tracked files with git ls-files.\n");
    exit(1);
}

$files = array_filter(explode("\0", $tracked), 'strlen');
$violations = [];
$checked = 0;

foreach ($files as $file) {
    if (! is_file($file)) {
        continue;
    }

    $contents = @file_get_contents($file);

    if ($contents === false || str_contains($contents, "\0")) {
        continue;
    }

    $checked++;
    $inConflict = false;

    foreach (preg_split('/\R/', $contents) as $index => $line) {
        $lineNumber = $index + 1;

        if (preg_match('/^<{7}(?:\s|$)/', $line) === 1) {
            $inConflict = true;
            $violations[] = sprintf('%s:%d starts a conflict block', $file, $lineNumber);

            continue;
        }

        if ($inConflict && preg_match('/^={7}(?:\s|$)/', $line) === 1) {
            $violations[] = sprintf('%s:%d conflict separator', $file, $lineNumber);

            continue;
        }

        if ($inConflict && preg_match('/^>{7}(?:\s|$)/', $line) === 1) {
            $inConflict = false;
            $violations[] = sprintf('%s:%d ends a conflict block', $file, $lineNumber);
        }
    }
}

if ($violations !== []) {
    fwrite(STDERR, "G1 conflict-marker gate failed:\n");

    foreach (array_slice($violations, 0, 25) as $violation) {
        fwrite(STDERR, "  {$violation}\n");
    }

    if (count($violations) > 25) {
        fwrite(STDERR, '  ... and '.(count($violations) - 25)." more\n");
    }

    exit(1);
}

echo "G1 conflict-marker gate: clean ({$checked} text files scanned).\n";
