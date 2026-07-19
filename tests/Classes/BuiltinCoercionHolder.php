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
 * One property per scalar target type, each seeded with a sentinel that no test payload produces.
 * Lenient mode coerces a mismatching scalar rather than rejecting it, and that matrix had no
 * coverage at all - a change that widened rejection to every builtin type passed the whole suite.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class BuiltinCoercionHolder
{
    public string $text = 'sentinel';

    public int $number = -1;

    public float $decimal = -1.5;

    public bool $flag = false;

    /**
     * Seeded the other way round on purpose. A bool has only two values, so a row expecting false
     * cannot discriminate against a property that already defaults to false - "coerced to false"
     * and "rejected, left untouched" would be the same observation. Rows expecting false use this
     * property, rows expecting true use the one above.
     */
    public bool $flagSeededTrue = true;

    /**
     * Deliberately declared without an element-type annotation. Naming an element type would make
     * this a CollectionType, which a different strategy resolves - the object-to-array cast would
     * then never reach the builtin strategy and the test asserting it would be vacuous.
     */
    public array $bag = ['sentinel' => 'sentinel'];

    public object $thing;
}
