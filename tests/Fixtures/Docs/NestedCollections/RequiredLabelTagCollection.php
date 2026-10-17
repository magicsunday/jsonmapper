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
 * A collection that implements ArrayAccess and Traversable but cannot receive the mapped elements.
 *
 * Its constructor declares a required parameter that is not the element list - the typed-collection
 * idiom that takes its element type rather than its elements. The constructor lane would hand it the
 * whole element array as that parameter, which it refuses, so the mapper must report the failure
 * through the MappingException hierarchy rather than let a native TypeError escape error collection.
 *
 * @implements IteratorAggregate<array-key, Tag>
 * @implements ArrayAccess<array-key, Tag>
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class RequiredLabelTagCollection implements IteratorAggregate, ArrayAccess
{
    /**
     * @var array<array-key, Tag>
     */
    private array $items = [];

    /**
     * Requires a label rather than the elements, so the constructor lane cannot fill it.
     *
     * @param string $label A required argument that is not the element list.
     */
    public function __construct(private readonly string $label)
    {
    }

    /**
     * Returns the label the collection was built with.
     *
     * @return string The label
     */
    public function getLabel(): string
    {
        return $this->label;
    }

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
     * Stores a tag, appending it when the offset is null.
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
