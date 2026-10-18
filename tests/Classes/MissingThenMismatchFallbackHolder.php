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
 * Holds a union whose first member is rejected and whose second member lacks a property.
 * The union search normalises the member order instead of keeping the declared one, so the
 * names are chosen to make it try the rejected member first.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class MissingThenMismatchFallbackHolder
{
    public MissingThenMismatchDto|MissingThenMismatchFallbackDto $value;
}
