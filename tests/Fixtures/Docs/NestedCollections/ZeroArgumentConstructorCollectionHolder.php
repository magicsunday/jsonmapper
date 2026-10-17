<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Docs\NestedCollections;

/**
 * Holds a collection whose explicit constructor takes no arguments, reached via the property path.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class ZeroArgumentConstructorCollectionHolder
{
    /**
     * The collection to fill, whose declared zero-parameter constructor still routes it to the
     * offsetSet lane rather than being handed the whole list.
     *
     * @var ZeroArgumentConstructorTagCollection
     */
    public ZeroArgumentConstructorTagCollection $tags;
}
