<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Docs\NestedCollections;

use ArrayObject;
use RuntimeException;

/**
 * An ArrayObject subclass that accepts its elements through the constructor and rejects offsetSet.
 *
 * Its constructor declares a parameter, so the mapper must hand it the whole list rather than
 * construct it empty and write through offsetSet. It is the control proving the fill decision keys
 * on whether the constructor takes arguments, not merely on whether it has required ones: an empty
 * construction followed by offsetSet would hit the throw below.
 *
 * @extends ArrayObject<int, Tag>
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class ConstructorFilledTagCollection extends ArrayObject
{
    /**
     * @param array<int, Tag> $elements Elements the collection is built from.
     */
    public function __construct(array $elements = [])
    {
        parent::__construct($elements);
    }

    /**
     * Rejects element-by-element writes, so the collection can only be filled through its constructor.
     *
     * @param mixed $key   Offset to write.
     * @param mixed $value Value to store.
     *
     * @return void
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        throw new RuntimeException('This collection is filled through its constructor, not offsetSet.');
    }
}
