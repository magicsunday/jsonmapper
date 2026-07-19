<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use ArrayObject;
use MagicSunday\JsonMapper\Attribute\ReplaceNullWithDefaultValue;

/**
 * A default that constructs a MUTABLE object, reached through the null-replacement attribute so a
 * payload of nulls exercises it once per element.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class SharedDefaultHolder
{
    /**
     * @param ArrayObject<array-key, mixed> $bag Collected values, defaulted per instance.
     */
    public function __construct(
        #[ReplaceNullWithDefaultValue]
        public ArrayObject $bag = new ArrayObject(),
    ) {
    }
}
