<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Enum;

/**
 * An int-backed enum. Its cases are deliberately numbered so a JSON payload carrying them as
 * strings looks plausible - which is exactly the loosely typed input that used to crash.
 */
enum SamplePriority: int
{
    case Low  = 1;
    case High = 2;
}
