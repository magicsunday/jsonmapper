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

/**
 * An ordinary data object that implements ArrayAccess but is not traversable.
 *
 * The negative control for the second half of the recognition rule. A collection needs both
 * interfaces, ArrayAccess to write each element and Traversable to read the result back. A class
 * offering only the write side is no collection: routing it to the collection factory would build it
 * from the payload's elements and drop every property below. Recognition must therefore require
 * Traversable as well, which this fixture proves by keeping its own fields on an object that is
 * array-accessible yet not iterable.
 *
 * @implements ArrayAccess<array-key, mixed>
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class ArrayAccessDataObject implements ArrayAccess
{
    /**
     * @var string
     */
    public string $title = 'untouched';

    /**
     * @var int
     */
    public int $count = -1;

    /**
     * Reports whether a value is stored at the given offset.
     *
     * @param array-key $offset Offset to check.
     *
     * @return bool TRUE when a value is stored at the offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return false;
    }

    /**
     * Returns null, since this object stores no array elements.
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
     * Ignores element writes, since this object is a data object rather than a container.
     *
     * @param array-key|null $offset Offset to write, or null to append.
     * @param mixed          $value  Value offered for storage.
     *
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
    }

    /**
     * Ignores element removal, since this object stores no array elements.
     *
     * @param array-key $offset Offset to remove.
     *
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
    }
}
