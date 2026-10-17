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

/**
 * A collection whose constructor takes the element list but also requires a second argument.
 *
 * The mapper hands the element list to the first parameter only, so a second required parameter can
 * never be satisfied. The pre-check reports this as a missing constructor argument before calling the
 * constructor, rather than letting a native ArgumentCountError escape error collection.
 *
 * @extends ArrayObject<int, Tag>
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class RequiredSecondArgumentTagCollection extends ArrayObject
{
    /**
     * Declares a required second argument the single element-list argument cannot satisfy.
     *
     * @param array<int, Tag> $elements The element list the mapper supplies.
     * @param string          $required A required argument the mapper cannot supply.
     */
    public function __construct(array $elements, private readonly string $required)
    {
        parent::__construct($elements);
    }

    /**
     * Returns the required argument the collection was built with.
     *
     * @return string The required argument
     */
    public function getRequired(): string
    {
        return $this->required;
    }
}
