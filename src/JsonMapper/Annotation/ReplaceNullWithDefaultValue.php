<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace MagicSunday\JsonMapper\Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * This annotation is used to inform the JsonMapper that an existing default value should be used when
 * setting a property, if the value derived from the JSON is a NULL value instead of the expected property type.
 *
 * This can be necessary, for example, in the case of a bad API design, if the API documentation defines a
 * certain type (e.g. array), but the API call itself then returns NULL if no data is available for a property
 * instead of an empty array that can be expected.
 *
 * @Annotation
 *
 * @Target({"PROPERTY"})
 */
final class ReplaceNullWithDefaultValue extends Annotation
{
}
