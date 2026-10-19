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
 * Fixture for the nesting depth limit, holding a union that recovers from a depth failure next to a
 * property that fails for another reason.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class DepthStaleInner
{
    /**
     * @var DepthChainNode|array<string, mixed>
     */
    public DepthChainNode|array $value = [];

    /**
     * @var int
     */
    public int $bad = 0;
}
