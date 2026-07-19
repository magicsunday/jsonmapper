<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use Stringable;

/**
 * The one composite value settype() converts meaningfully on a string target - it honours
 * __toString(). Pins that the composite rejection sweeps it up anyway, because the decision is
 * made on the target type rather than on the value's capabilities.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class StringableValue implements Stringable
{
    /**
     * Returns the string representation of this value.
     *
     * @return string Fixed representation used by the coercion tests
     */
    public function __toString(): string
    {
        return 'hi';
    }
}
