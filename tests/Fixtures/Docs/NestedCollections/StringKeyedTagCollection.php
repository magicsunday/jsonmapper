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
 * A string-keyed collection.
 *
 * Exercises the map-shaped payload path - a JSON object rather than a list, whose keys must
 * survive into the collection. Note that the declared KEY TYPE itself is inert: the factory
 * copies keys from the source verbatim and consults only the value type, so this fixture does not
 * discriminate that half of the re-wrap and does not claim to.
 *
 * @extends ArrayObject<string, Tag>
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class StringKeyedTagCollection extends ArrayObject
{
}
