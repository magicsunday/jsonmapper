<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Classes;

use DateInterval;
use DateTimeImmutable;

final class DateTimeHolder
{
    public DateTimeImmutable $createdAt;

    public ?DateInterval $timeout = null;
}
