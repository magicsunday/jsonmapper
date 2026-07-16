<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use MagicSunday\JsonMapper\Attribute\UnknownPropertyCollector;

/**
 * Fixture that misuses the collector attribute on a static property, so a test can assert the mapper
 * rejects the declaration: a static property is shared, not a per-instance sink.
 */
final class UnknownPropertyCollectorStaticEntity
{
    /**
     * @var array<string, mixed> The invalidly-static collector.
     */
    #[UnknownPropertyCollector]
    public static array $extra = [];

    /**
     * @param string $name The known property.
     */
    public function __construct(public readonly string $name = '')
    {
    }
}
