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
 * A property typed by the shape interface that cannot be null, so a failure inside the object a
 * class map built for it is reported on its own instead of being folded into a mismatch of the
 * property, as a nullable one does.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class RequiredShapeHolder
{
    /**
     * The nested shape, resolved per payload.
     */
    public Shape $shape;
}
