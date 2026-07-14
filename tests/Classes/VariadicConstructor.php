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
 * A class whose constructor ends in a variadic parameter, so a payload list must be spread across
 * the tail arguments.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class VariadicConstructor
{
    /**
     * @var array<string>
     */
    public array $tags;

    /**
     * @param string ...$tags The variadic tags
     */
    public function __construct(string ...$tags)
    {
        $this->tags = $tags;
    }
}
