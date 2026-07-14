<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

/**
 * A final readonly value object whose promoted constructor parameter uses camelCase, so a
 * snake_case payload key must be normalised before it matches the argument.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final readonly class CamelCaseReadonly
{
    /**
     * @param string $fullName The full name
     */
    public function __construct(
        public string $fullName,
    ) {
    }
}
