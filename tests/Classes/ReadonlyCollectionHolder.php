<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

/**
 * A final readonly object whose promoted constructor parameter is a typed collection, exercising
 * collection conversion in a constructor-argument position.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final readonly class ReadonlyCollectionHolder
{
    /**
     * @param list<ReadonlyValueObject> $items The nested value objects
     */
    public function __construct(
        public array $items,
    ) {
    }
}
