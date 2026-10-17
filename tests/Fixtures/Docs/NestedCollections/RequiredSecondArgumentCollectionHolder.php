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
 * Holds a collection whose constructor requires a second argument, reached via the property path.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class RequiredSecondArgumentCollectionHolder
{
    /**
     * The collection whose second required constructor argument the mapper cannot supply, so the
     * failure is reported rather than raised as a native ArgumentCountError.
     *
     * @var RequiredSecondArgumentTagCollection
     */
    public RequiredSecondArgumentTagCollection $tags;
}
