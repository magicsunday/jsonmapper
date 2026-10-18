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
 * Takes one value through its constructor and requires another one as a plain property.
 *
 * A payload that omits the property and supplies an unusable value for the argument leaves the
 * latest records holding a missing property while the construction fails for another argument.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class ConstructorArgumentAndRequiredPropertyDto
{
    public string $label;

    public function __construct(public readonly int $count)
    {
    }
}
