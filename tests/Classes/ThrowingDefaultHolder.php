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
 * A promoted default that is an EXPRESSION with a side effect. Reflection evaluates it when the
 * value is asked for, so a mapper that resolves defaults eagerly runs it even for a property the
 * payload supplies - and a throw from it escapes as a native error.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class ThrowingDefaultHolder
{
    public function __construct(
        public string $name = 'x',
        public ?ThrowingDefault $boom = new ThrowingDefault(),
    ) {
    }
}
