<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Shapes;

/**
 * A property whose docblock declares the null type itself.
 *
 * An odd declaration rather than an impossible one: it says the property holds nothing, and the
 * mapper answers it with nothing rather than trying to convert towards a type with no values.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class NullTypedHolder
{
    /**
     * @var null
     */
    public $nothing;
}
