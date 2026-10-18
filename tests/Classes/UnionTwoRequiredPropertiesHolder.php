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
 * Nests an object with two required properties under a union of two real members.
 *
 * Unlike a nullable declaration, which only adds null to one type and so has nothing to choose
 * between, this one makes the mapper try its members against the payload.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class UnionTwoRequiredPropertiesHolder
{
    public TwoRequiredPropertiesDto|int $dto = 0;
}
