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
 * A constructor parameter typed `parent`, fed from an untyped property.
 *
 * A relative type resolves against the DECLARING class, and `parent` is the case a plain
 * `self`/`static` shortcut would miss: it must resolve to the base rather than to the class the
 * mapper is building. Pins that resolution and that a raw array reaching the parameter is reported
 * rather than raising a native TypeError.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class ParentTypedConstructorHolder extends ParentTypedConstructorBase
{
    /**
     * Deliberately untyped and undocumented: the permissive resolution lets the raw payload reach
     * the `parent`-typed constructor parameter unconverted.
     */
    public $origin;

    public function __construct(parent $origin)
    {
        $this->origin = $origin;
    }
}
