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
