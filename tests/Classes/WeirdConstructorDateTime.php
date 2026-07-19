<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use DateTime;

/**
 * A date subclass whose constructor demands something other than a date string.
 *
 * createFromFormat() bypasses the constructor, so the ordinary path is unaffected. When a value
 * does not parse, the fallback reaches `new` and PHP raises a TypeError - a native Error that no
 * MappingException catch collects, leaving the caller with a fatal where a report entry was
 * promised.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class WeirdConstructorDateTime extends DateTime
{
    /**
     * @param int $timestamp Unix timestamp the caller must supply
     */
    public function __construct(int $timestamp)
    {
        parent::__construct('@' . $timestamp);
    }
}
