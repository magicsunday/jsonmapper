<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use MagicSunday\JsonMapper\Attribute\ReplaceNullWithDefaultValue;

/**
 * A property marked ReplaceNullWithDefaultValue alongside a NON-promoted constructor parameter
 * that merely shares its name and carries an unrelated type. Used to pin that only a promoted
 * parameter may supply a property default, since a same-named plain parameter has no type
 * relationship to the property.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class SameNamedConstructorParameterHolder
{
    #[ReplaceNullWithDefaultValue]
    public int $count;

    /**
     * @param string $count Unrelated parameter that only shares the property name.
     */
    public function __construct(string $count = 'oops')
    {
    }
}
