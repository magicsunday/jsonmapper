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
 * The union member that is tried first: its name sorts before {@see UnionTrailingDto}, and the
 * normalised order of a union sorts its members by type name.
 *
 * It requires a property the trailing member does not, so a payload carrying only `first` lacks
 * something here and nothing there. The member tried first is the one used.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class UnionLeadingDto
{
    public string $first;

    public string $second;
}
