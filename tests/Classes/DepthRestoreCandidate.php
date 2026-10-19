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
 * Fixture for the nesting depth limit, with a property that is refused for its depth followed by a
 * union that recovers from the same limit.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class DepthRestoreCandidate
{
    /**
     * @var DepthChainLink
     */
    public DepthChainLink $bad;

    /**
     * @var DepthChainNode|array<string, mixed>
     */
    public DepthChainNode|array $good = [];
}
