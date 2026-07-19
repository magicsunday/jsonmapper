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
 * Collections whose ELEMENT type is a union or nullable.
 *
 * Union resolution lived on the property path only, so a collection element declared this way
 * matched no strategy and was handed back unconverted.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class UnionElementCollectionHolder
{
    /**
     * @var array<int, Simple|null>
     */
    public array $nullableItems = [];

    /**
     * @var array<int, Simple|string>
     */
    public array $unionItems = [];

    /**
     * A plain, non-nullable element type - no union involved. The null guard applied on a
     * top-level property has to apply here too, or the list ends up holding a value its own
     * docblock forbids.
     *
     * @var array<int, string>
     */
    public array $names = [];

    /**
     * A plain, non-nullable OBJECT element type - no union, no null member. This is what separates
     * the null guard from the strategy that runs after it: a builtin element type refuses a null
     * outright, whereas an object type reaches the object strategy, which instantiates a class
     * needing no constructor arguments and thereby invents an entry the payload never contained.
     *
     * @var array<int, Simple>
     */
    public array $objectItems = [];

    /**
     * A union with NO null member. An element type that forbids null is the case that separates
     * "the null strategy claims every null" from "null is checked against the declared type".
     *
     * @var array<int, Simple|string>
     */
    public array $nonNullableItems = [];
}
