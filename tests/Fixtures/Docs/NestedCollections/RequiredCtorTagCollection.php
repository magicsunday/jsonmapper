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
 * A collection whose constructor REQUIRES the elements, so it cannot be built empty.
 *
 * The mapper must hand such a wrapper its elements through the constructor rather than construct it
 * empty and fill it through offsetSet, or construction fails. It is the control for the constructor
 * lane of the wrapper-fill decision, the counterpart to the offsetSet lane a hand-rolled container
 * with a default constructor takes.
 *
 * @extends ArrayObject<int, Tag>
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class RequiredCtorTagCollection extends ArrayObject
{
    /**
     * @param array<int, Tag> $elements Elements the collection is built from.
     */
    public function __construct(array $elements)
    {
        parent::__construct($elements);
    }
}
