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
 * Nests an object with two required properties under a plain, non-nullable property.
 *
 * The control for {@see NullableTwoRequiredPropertiesHolder}: both resolve the same nested class,
 * but only the nullable declaration routes through the union path.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class TwoRequiredPropertiesHolder
{
    public TwoRequiredPropertiesDto $dto;
}
