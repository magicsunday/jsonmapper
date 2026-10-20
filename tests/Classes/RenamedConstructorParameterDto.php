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
 * Receives the value of its required property through a constructor parameter of another name.
 *
 * Strict validation names the property, the failed construction names the parameter.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class RenamedConstructorParameterDto
{
    public string $title;

    public function __construct(string $heading)
    {
        $this->title = $heading;
    }
}
