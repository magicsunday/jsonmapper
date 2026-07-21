<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\EntryPoint;

use MagicSunday\Test\Classes\Collection;

/**
 * An abstract collection wrapper, so a property typed with it exercises the wrapper-instantiation
 * lane inside CollectionFactory rather than the entry point.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 *
 * @extends Collection<array-key, Circle>
 */
abstract class AbstractShapeCollection extends Collection
{
}
