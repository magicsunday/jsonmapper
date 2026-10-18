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
 * Nests an object with two required properties under a NULLABLE property.
 *
 * A nullable class type is a union with null, so the nested object is converted through the union
 * path rather than the plain object path. Strict mode has to validate it either way.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class NullableTwoRequiredPropertiesHolder
{
    public ?TwoRequiredPropertiesDto $dto = null;
}
