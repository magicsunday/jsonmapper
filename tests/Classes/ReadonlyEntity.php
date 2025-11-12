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
 * Fixture with a readonly promoted property.
 */
class ReadonlyEntity
{
    public function __construct(public readonly string $id = 'initial')
    {
    }
}
