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
 * Accepts the payload of MissingThenMismatchDto with one missing property of its own.
 *
 * It is the member that wins once the first one is rejected on its type failure.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class MissingThenMismatchFallbackDto
{
    public string $required;

    /**
     * @var array<array-key, mixed>
     */
    public array $nested = [];

    public string $count = '';
}
