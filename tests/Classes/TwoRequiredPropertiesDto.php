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
 * Two independent properties, so that an empty payload produces two separate strict-mode
 * violations. A single-property fixture cannot tell "collects everything" apart from "aborts on
 * the first failure", because both yield exactly one record.
 *
 * Neither property declares a default: a default makes the property optional, so strict mode would
 * not report it missing and the fixture would stop discriminating.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class TwoRequiredPropertiesDto
{
    public string $first;

    public string $second;
}
