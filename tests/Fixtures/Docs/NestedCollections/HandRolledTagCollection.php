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
 * A hand-rolled collection that keeps its elements in a declared storage property, the idiom a
 * container class uses when it does not extend ArrayObject.
 *
 * It is recognised as a collection by the two interfaces it implements, ArrayAccess and
 * Traversable, not by the absence of properties - a visible storage array is exactly what this
 * shape has. It is filled through offsetSet, the public door every such container offers, and it
 * deliberately has no constructor taking the elements, so the mapper cannot fall back to passing
 * them to one.
 *
 * @implements IteratorAggregate<array-key, Tag>
 * @implements ArrayAccess<array-key, Tag>
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class HandRolledTagCollection implements IteratorAggregate, ArrayAccess
{
    /**
     * @var array<array-key, Tag>
     */
    private array $items = [];

    /**
     * Returns an iterator over the contained tags.
     *
     * @return Traversable<array-key, Tag> Iterator over the contained tags
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Reports whether a tag is stored at the given offset.
     *
     * @param array-key $offset Offset to check.
     *
     * @return bool TRUE when a tag is stored at the offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Returns the tag stored at the given offset, or null when none is.
     *
     * @param array-key $offset Offset to read.
     *
     * @return Tag|null The stored tag, or null
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * Stores a tag, appending it when the offset is null. This is the door the mapper fills through.
     *
     * @param array-key|null $offset Offset to write, or null to append.
     * @param Tag            $value  Tag to store.
     *
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;

            return;
        }

        $this->items[$offset] = $value;
    }

    /**
     * Removes the tag stored at the given offset.
     *
     * @param array-key $offset Offset to remove.
     *
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }
}
