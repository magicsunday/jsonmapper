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
 * A constructor parameter typed `self`, fed from an untyped property.
 *
 * On the older supported PHP versions reflection reports this parameter's type as the literal
 * `self` rather than the class it stands for, so a guard keying on the reported spelling would wave
 * every value through and let a native TypeError escape. Pins that the relative name is resolved
 * against the declaring class and a wrong value is reported instead.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class SelfTypedConstructorHolder
{
    /**
     * Deliberately untyped and undocumented: with no type metadata the resolver stays permissive
     * and the payload reaches the constructor unconverted, which is what lets a raw array meet the
     * `self` parameter.
     */
    public $child;

    public function __construct(self $child)
    {
        $this->child = $child;
    }
}
