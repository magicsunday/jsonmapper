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
 * A hand-rolled collection that declares an explicit constructor taking no arguments.
 *
 * It is the control for the second half of the fill gate: a class with no constructor at all and a
 * class with a declared zero-parameter constructor must both take the offsetSet lane. Keying the gate
 * on the required-parameter count would still route this one correctly, so the discriminator is a
 * declared constructor whose parameter count is zero - distinct from the no-constructor case. It fills
 * through offsetSet and stores its elements in a private property.
 *
 * @implements IteratorAggregate<array-key, Tag>
 * @implements ArrayAccess<array-key, Tag>
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class ZeroArgumentConstructorTagCollection implements IteratorAggregate, ArrayAccess
{
    /**
     * @var array<array-key, Tag>
     */
    private array $items = [];

    /**
     * Builds an empty collection, declaring a constructor that takes no arguments.
     */
    public function __construct()
    {
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
