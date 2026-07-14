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
 * A final readonly value object with required, promoted constructor arguments — the shape that
 * cannot be hydrated by property assignment.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final readonly class ReadonlyValueObject
{
    /**
     * @param string $name The name
     * @param int    $age  The age
     */
    public function __construct(
        public string $name,
        public int $age,
    ) {
    }
}
