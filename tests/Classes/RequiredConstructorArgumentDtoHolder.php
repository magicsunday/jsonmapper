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
 * Nests the immutable value object, so the scalar-on-object rejection can be pinned on the
 * property path as well as at the top level. Those are two different callers.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class RequiredConstructorArgumentDtoHolder
{
    public ?RequiredConstructorArgumentDto $dto = null;
}
