<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\TypePrecedence;

/**
 * The same contradiction on a promoted constructor parameter.
 *
 * This is the lane the property write guard does not cover: the value reaches `new $className()`
 * and a native TypeError escaped the report entirely.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class WideningConstructorHolder
{
    /**
     * @param int|null $value The promoted property whose docblock contradicts its native type.
     */
    public function __construct(
        public readonly int $value = 7,
    ) {
    }
}
