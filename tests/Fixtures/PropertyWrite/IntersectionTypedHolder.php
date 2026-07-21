<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\PropertyWrite;

/**
 * A property whose declared type the mapper cannot model.
 *
 * Neither Symfony's PropertyInfo nor the reflection fallback represents an intersection, so the
 * type resolver falls back to nullable mixed - which accepts the payload untouched and leaves the
 * declared type to reject it at the write.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class IntersectionTypedHolder
{
    /**
     * A value satisfying both markers, or none at all.
     */
    public (MarkerA&MarkerB)|null $both = null;
}
