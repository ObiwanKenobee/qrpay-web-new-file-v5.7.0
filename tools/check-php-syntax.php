<?php

declare(strict_types=1);

/**
 * ADR 0016 G2 — PHP syntax gate.
 *
 * Lints every git-tracked PHP file with the running PHP binary. This is
 * deliberately broader than the app directory: the boot blocker was in a model
 * loaded indirectly, and helper files are autoloaded on every request.
 */
$root = dirname(__DIR__);
chdir($root);

$tracked = shell_exec("git ls-files -z -- '*.php'");

if (! is_string($tracked) || $tracked === '') {
    fwrite(STDERR, "Unable to enumerate tracked PHP files with git ls-files.\n");
    exit(1);
}

$files = array_values(array_filter(explode("\0", $tracked), 'strlen'));
$failures = [];
$checked = 0;
$bladeSkipped = 0;

foreach ($files as $file) {
    if (! is_file($file)) {
        continue;
    }

    if (str_ends_with($file, '.blade.php')) {
        // Blade templates are not standalone PHP; they are compiled by Laravel.
        $bladeSkipped++;

        continue;
    }

    $output = [];
    $exitCode = 0;
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $output, $exitCode);
    $checked++;

    if ($exitCode !== 0) {
        $failures[$file] = implode("\n", $output);
    }
}

if ($failures !== []) {
    fwrite(STDERR, 'G2 PHP syntax gate failed for '.count($failures)." file(s):\n");

    foreach ($failures as $file => $output) {
        fwrite(STDERR, "  {$file}\n    ".str_replace("\n", "\n    ", trim($output))."\n");
    }

    exit(1);
}

echo "G2 PHP syntax gate: clean ({$checked} files linted with PHP ".PHP_VERSION."; {$bladeSkipped} Blade templates skipped).\n";
