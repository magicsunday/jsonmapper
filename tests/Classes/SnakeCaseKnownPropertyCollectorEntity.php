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
 * Fixture with a camelCase property a snake_case payload key converts onto, alongside a collector.
 *
 * It pins that unknown-ness is decided on the CONVERTED name: `full_name` camelises to the declared
 * `fullName` and is mapped, while a key that converts onto no property is collected under its
 * original spelling.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class SnakeCaseKnownPropertyCollectorEntity
{
    /**
     * @param string               $fullName The known property, reached from a snake_case key.
     * @param array<string, mixed> $extra    The sink for unknown source keys, keyed by original name.
     */
    public function __construct(
        public readonly string $fullName = '',
        #[UnknownPropertyCollector]
        public readonly array $extra = [],
    ) {
    }
}
