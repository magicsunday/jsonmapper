<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\EntryPoint;

/**
 * A property typed by the shape interface, so a class map resolves its concrete class from the
 * nested payload - the lane on which a resolver-produced name must not be echoed.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class ShapeHolder
{
    /**
     * The nested shape, resolved per payload.
     */
    public ?Shape $shape = null;
}
