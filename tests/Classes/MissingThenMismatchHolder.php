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
 * Holds a union whose first member fails on a type after a nested object lacks properties.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class MissingThenMismatchHolder
{
    /**
     * @var array<array-key, mixed>|MissingThenMismatchDto
     */
    public MissingThenMismatchDto|array $value = [];
}
