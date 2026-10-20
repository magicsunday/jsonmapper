<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\EntryPoint;

namespace MagicSunday\Test\Fixtures\EntryPoint;

/**
 * A shape that arrives through the constructor, so the object a class map built for it meets the
 * constructor guard and not the property write.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final readonly class ConstructedShapeHolder
{
    /**
     * @param Shape $shape The nested shape, resolved per payload.
     */
    public function __construct(public Shape $shape)
    {
    }
}
