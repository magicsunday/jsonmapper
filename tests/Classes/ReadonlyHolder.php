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
 * A final readonly object nesting another readonly value object, exercising recursive
 * constructor hydration.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final readonly class ReadonlyHolder
{
    /**
     * @param string              $label The label
     * @param ReadonlyValueObject $value The nested readonly value object
     */
    public function __construct(
        public string $label,
        public ReadonlyValueObject $value,
    ) {
    }
}
