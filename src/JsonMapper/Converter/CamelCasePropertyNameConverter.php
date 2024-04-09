<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace MagicSunday\JsonMapper\Converter;

use Doctrine\Common\Inflector\Inflector;

/**
 * CamelCasePropertyNameConverter.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 *
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class CamelCasePropertyNameConverter implements PropertyNameConverterInterface
{
    /**
     * Convert the specified JSON property name to its PHP property name.
     *
     * @param string $name
     *
     * @return string
     */
    public function convert($name)
    {
        return Inflector::camelize($name);
    }
}
