<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\TypePrecedence;

/**
 * A natively typed constructor parameter fed from a property that declares no type at all.
 *
 * Nothing widens here - there is no docblock to contradict anything. The property carries no type
 * metadata, so the resolver's permissive fallback lets any value through conversion and straight
 * into a parameter that is natively `int`. Pins that the guard keys on the declaration rather than
 * on the presence of a contradiction.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class UntypedConstructorHolder
{
    /**
     * Deliberately untyped and undocumented: it is the property the payload is mapped onto, and
     * with no type metadata at all the resolver falls back to its permissive default.
     */
    public $count;

    /**
     * The promoted `$label` is required, which is what makes the mapper build through the
     * constructor at all; `$count` stays NON-promoted so it lands on the untyped property above
     * rather than declaring a type of its own.
     *
     * @param string $label Required, so the constructor lane is taken.
     * @param int    $count Value the untyped property is filled from.
     */
    public function __construct(public readonly string $label, int $count = 3)
    {
        $this->count = $count;
    }
}
