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
 * Takes two required values through its constructor.
 *
 * Both omissions are reported before construction fails for the first of them, so the failed
 * construction has to find its own omission behind the record of the other one.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final readonly class TwoRequiredConstructorArgumentsDto
{
    public function __construct(
        public string $first,
        public string $second,
    ) {
    }
}
