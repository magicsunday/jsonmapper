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
 * Object with two properties whose names several payload spellings fold onto.
 *
 * `id`, `ID` and `Id` name the first property. `user_id`, `user-id` and `userId` name the second.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class AmbiguousSpellingTarget
{
    /**
     * Identifier that several spellings of a payload key name.
     */
    public int $id = 0;

    /**
     * Identifier whose snake case, dash and camel case spellings name one property.
     */
    public string $userId = '';
}
