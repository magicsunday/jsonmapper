<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

/*
 * Smoke test for a production install (composer install --no-dev).
 *
 * The unit test suite always runs with development dependencies present and therefore cannot
 * detect a runtime dependency that is only declared under "require-dev". This script exercises
 * the documented default entry point against an installation that has no reason to pull the
 * development dependencies - which is every consumer installation.
 */

use MagicSunday\JsonMapper;
use Psr\Cache\CacheItemPoolInterface;

// STDERR only exists in the CLI SAPI, so this branch cannot use it - it is precisely the branch
// that runs when the script was not started from the command line.
if (\PHP_SAPI !== 'cli') {
    echo "This script supports command line usage only. Please check your command.\n";

    exit(1);
}

require __DIR__ . '/../../.build/vendor/autoload.php';

/**
 * Minimal mapping target covering the two extractor paths: a natively typed property and a
 * property whose type is only available from its docblock.
 */
final class ProductionInstallSmokeTarget
{
    public string $name = '';

    /**
     * @var array<int, string>
     */
    public $tags = [];
}

// The mapper accepts a PSR-6 pool and type-hints this interface in its own signatures, so
// psr/cache is a runtime requirement as well. The default construction path below passes no
// pool, which would leave that requirement unexercised - an absent interface only surfaces
// when something actually resolves it.
if (!interface_exists(CacheItemPoolInterface::class)) {
    fwrite(\STDERR, "psr/cache is not installed, although the mapper type-hints its interfaces.\n");

    exit(1);
}

$mapper = JsonMapper::createWithDefaults();

$result = $mapper->map(
    ['name' => 'jsonmapper', 'tags' => ['json', 'mapper']],
    ProductionInstallSmokeTarget::class,
);

if (!$result instanceof ProductionInstallSmokeTarget) {
    fwrite(\STDERR, "Mapping did not return the requested target type.\n");

    exit(1);
}

if ($result->name !== 'jsonmapper') {
    fwrite(\STDERR, "Natively typed property was not mapped.\n");

    exit(1);
}

if ($result->tags !== ['json', 'mapper']) {
    fwrite(\STDERR, "Docblock typed property was not mapped.\n");

    exit(1);
}

echo "Production install smoke test passed.\n";
