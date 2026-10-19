<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use MagicSunday\JsonMapper\Attribute\UnknownPropertyCollector;

/**
 * A collector that is written through a variadic setter.
 *
 * The setter receives the collected map spread into its arguments, so a string key becomes a named
 * argument and an integer key a positional one.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class UnknownPropertyCollectorVariadicEntity
{
    /**
     * @var array<array-key, string>
     */
    #[UnknownPropertyCollector]
    public array $extra = [];

    /**
     * @param string ...$extra The collected values, positional ones first and named ones after them.
     */
    public function setExtra(string ...$extra): void
    {
        $this->extra = $extra;
    }
}
