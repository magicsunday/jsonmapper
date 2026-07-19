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
 * The singly nested shape - a collection of objects, one level deep.
 *
 * The property deliberately carries no generic docblock, so the element type can only come from
 * the collection class's own "extends" annotation. That is the difference from {@see Article},
 * whose property names the element type itself, and it is what the doubly nested recipe test
 * never exercises.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class SinglyNestedArticle
{
    /**
     * @var TagCollection
     */
    public TagCollection $tags;
}
