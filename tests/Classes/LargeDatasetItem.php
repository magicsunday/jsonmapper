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
 * Represents a single entry used to stress-test mapping performance.
 */
final class LargeDatasetItem
{
    public int $identifier;

    public string $label;

    public bool $active;
}
