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
 * Fixture for the nesting depth limit, one of two classes that nest in each other through
 * collections whose values are a union.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class DepthUnionCollectionA
{
    /**
     * Number of instances built, to tell work that grows with the depth from work that doubles per level.
     */
    public static int $built = 0;

    /**
     * @var array<int, DepthUnionCollectionA|DepthUnionCollectionB>|array<string, DepthUnionCollectionA|DepthUnionCollectionB>|null
     */
    public ?array $child = null;

    public function __construct()
    {
        ++self::$built;
    }
}
