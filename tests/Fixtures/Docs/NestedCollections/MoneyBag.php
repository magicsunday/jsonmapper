<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Docs\NestedCollections;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * A traversable, propertyless class that a caller converts through a registered handler.
 *
 * It is exactly the shape the container heuristic recognises, which is the point: a handler
 * registered via addType() has to win, or the documented escape hatch stops being one.
 *
 * @implements IteratorAggregate<int, Tag>
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class MoneyBag implements IteratorAggregate
{
    /**
     * @param int $amount Amount the handler extracted from the payload
     */
    public function __construct(public readonly int $amount = 0)
    {
    }

    /**
     * Returns an empty iterator; the contents are irrelevant to what this fixture pins.
     *
     * @return Traversable<int, Tag> Always empty
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator([]);
    }
}
