<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

/*
 * Fails when line coverage falls below a threshold.
 *
 * PHPUnit produces a Clover report but does not itself enforce a minimum, so the "coverage >= 90 %"
 * definition of done in AGENTS.md was never machine-checked. This script reads the Clover report's
 * project-level metrics and exits non-zero when the covered-statement ratio is under the threshold,
 * turning the documented target into a gate.
 *
 * Usage: php coverage-gate.php <clover.xml> [threshold-percent]
 */

$cloverPath = $argv[1] ?? '';

// A non-numeric threshold argument would coerce to 0.0 and silently turn the gate into an
// always-pass, defeating its purpose without any signal. Reject it - and anything outside 0-100 -
// as a usage error rather than let a misconfiguration disable the gate.
if (isset($argv[2]) && !is_numeric($argv[2])) {
    fwrite(\STDERR, sprintf("Coverage threshold must be numeric, got: %s\n", $argv[2]));

    exit(2);
}

$threshold = isset($argv[2]) ? (float) $argv[2] : 90.0;

if (($threshold < 0.0) || ($threshold > 100.0)) {
    fwrite(\STDERR, sprintf("Coverage threshold must be between 0 and 100, got: %s\n", $argv[2] ?? ''));

    exit(2);
}

if (($cloverPath === '') || !is_file($cloverPath)) {
    fwrite(\STDERR, sprintf("Coverage report not found: %s\n", $cloverPath === '' ? '<missing argument>' : $cloverPath));

    exit(2);
}

$document = new DOMDocument();

// Take libxml errors internal so a malformed report is reported once, by the branch below, rather
// than also emitting a raw libxml warning to the CI log ahead of it - the house bar is clean CLI
// output, and this is the parse-error path the script means to own.
$previousUseInternalErrors = libxml_use_internal_errors(true);
$loaded                    = $document->load($cloverPath);
libxml_clear_errors();
libxml_use_internal_errors($previousUseInternalErrors);

if ($loaded === false) {
    fwrite(\STDERR, sprintf("Coverage report could not be parsed: %s\n", $cloverPath));

    exit(2);
}

$metricsNodes = (new DOMXPath($document))->query('/coverage/project/metrics');

if (($metricsNodes === false) || ($metricsNodes->length === 0)) {
    fwrite(\STDERR, "Coverage report has no project metrics element.\n");

    exit(2);
}

$metrics = $metricsNodes->item(0);

if (!$metrics instanceof DOMElement) {
    fwrite(\STDERR, "Coverage report project metrics element is malformed.\n");

    exit(2);
}

$statements = (int) $metrics->getAttribute('statements');
$covered    = (int) $metrics->getAttribute('coveredstatements');

if ($statements === 0) {
    fwrite(\STDERR, "Coverage report counts no statements.\n");

    exit(2);
}

$percent = ($covered / $statements) * 100.0;

printf(
    "Line coverage: %s%% (%d/%d statements), threshold %s%%.\n",
    number_format($percent, 2),
    $covered,
    $statements,
    number_format($threshold, 2),
);

if ($percent < $threshold) {
    fwrite(\STDERR, sprintf("Coverage %s%% is below the required %s%%.\n", number_format($percent, 2), number_format($threshold, 2)));

    exit(1);
}

exit(0);
