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
 * The legitimate direction: the docblock REFINES the native type.
 *
 * `array` says nothing about the elements; `string[]` narrows it. This is the library's core
 * capability and must keep working - a rule of "native always wins" would break it.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class RefiningPropertyHolder
{
    /**
     * The element type must be CONVERTED for this to discriminate: with a native `array` and no
     * docblock, string payload elements would pass through unchanged, so a `string[]` refinement
     * over string input proves nothing. Integers do.
     *
     * @var int[]
     */
    public array $numbers = [];

    /**
     * A natively nullable property whose docblock agrees - nullability the property really has is
     * not stripped.
     *
     * @var int|null
     */
    public ?int $optional = null;
}
