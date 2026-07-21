<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\ReplaceNull;

use MagicSunday\JsonMapper\Attribute\ReplaceNullWithDefaultValue;
use MagicSunday\Test\Fixtures\Enum\SampleStatus;

/**
 * A property that is never null, declared with the default it falls back to.
 *
 * An enum case is one of the few non-null defaults a typed property can carry - object defaults
 * are not constant expressions - which is what makes this the shape where "the conversion produced
 * null" and "the default is usable" can both be true.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class StatusHolder
{
    /**
     * Status of the record.
     */
    #[ReplaceNullWithDefaultValue]
    public SampleStatus $status = SampleStatus::Inactive;
}
