<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * This annotation is used to inform the JsonMapper to replace a one or more properties with another one. It's
 * used in class context.
 *
 * To replace the property "bar" with a property "foo", add the following to the class doc block.
 *
 *    @ReplaceProperty("foo", replaces="bar")
 *
 * @Annotation
 *
 * @Target({"CLASS"})
 */
final class ReplaceProperty extends Annotation
{
    /**
     * @var string
     */
    public string $replaces;
}
