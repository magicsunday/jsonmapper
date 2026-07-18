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
 * A union of two scalar types. Candidate selection has to reject a value matching neither, and
 * must do so independently of whether the caller asked for errors to be collected.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class UnionScalarHolder
{
    /**
     * The sentinel is a string no coercion can produce. An int sentinel would collide with what
     * casting an unmatched value to the first union member yields, so it could not tell an
     * untouched property apart from a wrongly coerced one.
     */
    public int|string $value = 'untouched';
}
