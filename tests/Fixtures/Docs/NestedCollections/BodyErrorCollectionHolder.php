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
 * Holds a collection whose constructor body raises a native TypeError, reached via the property path.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class BodyErrorCollectionHolder
{
    /**
     * The collection whose constructor accepts the elements and then fails in its own body, so the
     * native error must surface rather than be masked as a mapping failure.
     *
     * @var BodyErrorTagCollection
     */
    public BodyErrorTagCollection $tags;
}
