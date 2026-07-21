<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\TypePrecedence;

/**
 * A promoted parameter whose docblock widens beyond nullability.
 *
 * Nullability is only the most common way a docblock oversteps its declaration. Pins that the guard
 * covers the whole class of widening rather than the one shape that prompted it.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class ScalarWideningConstructorHolder
{
    /**
     * @param int|string $value Value to store.
     */
    public function __construct(public readonly int $value = 7)
    {
    }
}
