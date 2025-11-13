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
 * Represents the root element for large dataset mapping tests.
 */
final class LargeDatasetRoot
{
    /**
     * @var LargeDatasetItem[]
     */
    public array $items = [];
}
