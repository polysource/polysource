<?php

declare(strict_types=1);

/*
 * Polysource coverage gate.
 *
 * Reads a Clover XML report and fails (exit 1) when the line coverage
 * of `packages/core/src` drops below the threshold. Used by both
 * `make coverage` locally and the CI workflow so a regression in core
 * coverage cannot land on main.
 *
 * Usage:
 *   php scripts/coverage-gate.php <clover.xml> [threshold-percent=90]
 */

if ($argc < 2) {
    fwrite(\STDERR, "usage: php scripts/coverage-gate.php <clover.xml> [threshold-percent=90]\n");
    exit(2);
}

$cloverPath = $argv[1];
$threshold = isset($argv[2]) ? (float) $argv[2] : 90.0;

if (!is_readable($cloverPath)) {
    fwrite(\STDERR, "coverage-gate: clover file not readable: {$cloverPath}\n");
    exit(2);
}

libxml_use_internal_errors(true);
$xml = simplexml_load_file($cloverPath);
if (false === $xml) {
    fwrite(\STDERR, "coverage-gate: failed to parse {$cloverPath}\n");
    exit(2);
}

$total = 0;
$covered = 0;
$files = 0;

foreach ($xml->xpath('//file') as $file) {
    $name = (string) $file['name'];
    if (!str_contains($name, '/core/src/')) {
        continue;
    }
    foreach ($file->line as $line) {
        if ('stmt' !== (string) $line['type']) {
            continue;
        }
        ++$total;
        if ((int) $line['count'] > 0) {
            ++$covered;
        }
    }
    ++$files;
}

if (0 === $total) {
    fwrite(\STDERR, "coverage-gate: no executable statements found under packages/core/src\n");
    exit(2);
}

$percent = $covered / $total * 100;
printf("core coverage: %d/%d statements (%.2f%%) across %d files — threshold %.2f%%\n", $covered, $total, $percent, $files, $threshold);

if ($percent + 0.001 < $threshold) {
    fwrite(\STDERR, "coverage-gate: FAIL — coverage below threshold\n");
    exit(1);
}

echo "coverage-gate: OK\n";
exit(0);
