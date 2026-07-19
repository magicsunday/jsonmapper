<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use DateTimeInterface;

/**
 * An interface of one's own extending DateTimeInterface.
 *
 * Legal PHP, and the case that separates an instantiability check from comparing against
 * DateTimeInterface by name: the latter would claim this type and reach `new`, raising a native
 * Error that no MappingException catch can collect.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
interface CustomDateTimeInterface extends DateTimeInterface
{
}
