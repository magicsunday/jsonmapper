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
 * Holds a collection whose constructor requires its elements, so the mapper reaches it through the
 * property path and must fill it the constructor way.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class RequiredCtorCollectionHolder
{
    /**
     * The collection to fill, whose constructor requires its elements so it cannot be built empty.
     *
     * @var RequiredCtorTagCollection
     */
    public RequiredCtorTagCollection $tags;
}
