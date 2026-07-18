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
 * Nests the immutable value object under a NON-nullable property. The nullable sibling routes
 * through the union path, which trims recorded errors before rethrowing - so only this shape can
 * observe how many records a single rejected value actually produces.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class NonNullableDtoHolder
{
    public RequiredConstructorArgumentDto $dto;
}
