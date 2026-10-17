<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Docs\NestedCollections;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * A traversable, array-accessible class that a caller converts through a registered handler.
 *
 * It implements both ArrayAccess and Traversable, exactly the interface pair the container heuristic
 * recognises, which is the point: a handler registered via addTypeHandler() has to win over that
 * heuristic, or the documented escape hatch stops being one.
 *
 * @implements IteratorAggregate<int, Tag>
 * @implements ArrayAccess<array-key, Tag>
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class MoneyBag implements IteratorAggregate, ArrayAccess
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

    /**
     * Reports whether a value is stored at the given offset; always false for this fixture.
     *
     * @param array-key $offset Offset to check.
     *
     * @return bool Always false
     */
    public function offsetExists(mixed $offset): bool
    {
        return false;
    }

    /**
     * Returns null; this fixture stores no array elements.
     *
     * @param array-key $offset Offset to read.
     *
     * @return null Always null
     */
    public function offsetGet(mixed $offset): mixed
    {
        return null;
    }

    /**
     * Ignores element writes; the registered handler, not the container heuristic, fills this class.
     *
     * @param array-key|null $offset Offset to write, or null to append.
     * @param Tag            $value  Value offered for storage.
     *
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
    }

    /**
     * Ignores element removal; this fixture stores no array elements.
     *
     * @param array-key $offset Offset to remove.
     *
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
    }
}
