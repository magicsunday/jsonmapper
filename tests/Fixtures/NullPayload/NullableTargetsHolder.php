<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\NullPayload;

use MagicSunday\Test\Fixtures\Enum\SampleStatus;

/**
 * Two kinds of target that accept a null, and a starting value on each.
 *
 * A nullable class type is a union of the class and null, so a null for it is settled by the union
 * handling. A property declared mixed accepts a null without being a union, which makes it the
 * target whose null is settled before the strategy chain. The type is mixed on purpose, because
 * that is the shape under test.
 *
 * The non-null starting value is what makes a mapped null observable, since starting from null
 * would leave a skipped assignment and an assigned null looking the same.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class NullableTargetsHolder
{
    /**
     * Status of the record, or null when it has none.
     */
    public ?SampleStatus $status = SampleStatus::Inactive;

    /**
     * Free-form value that accepts anything, null included.
     */
    public mixed $anything = 'initial';
}
