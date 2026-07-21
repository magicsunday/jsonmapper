<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Shapes;

use function array_values;

/**
 * A constructor whose tail is variadic.
 *
 * A variadic parameter consumes "the rest", which a JSON object has no notion of - there is no key
 * that means it. Skipping it leaves the object built from the parameters that do have names.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class VariadicConstructorHolder
{
    /**
     * @var list<string>
     */
    public array $tags;

    /**
     * @param int    $id      Identifier of the record.
     * @param string ...$tags Labels attached to it.
     */
    public function __construct(public int $id, string ...$tags)
    {
        $this->tags = array_values($tags);
    }
}
