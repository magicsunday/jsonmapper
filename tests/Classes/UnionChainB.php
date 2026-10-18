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
 * One of two object types that each hold a union of both as their child, with a required property
 * the payload of the test leaves out.
 *
 * The union nests in its own members, which is the shape where trying every member at every level
 * multiplies the work. The counter lets a test observe how many objects were built, and the one test
 * that reads it resets it first.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class UnionChainB
{
    /**
     * Number of instances built since the counter was last reset.
     */
    public static int $built = 0;

    public string $requiredB;

    public UnionChainA|UnionChainB|null $child = null;

    public function __construct()
    {
        ++self::$built;
    }
}
