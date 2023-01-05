<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Converter;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;

/**
 * CamelCasePropertyNameConverter
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class CamelCasePropertyNameConverter implements PropertyNameConverterInterface
{
    /**
     * @var Inflector
     */
    private $inflector;

    /**
     * CamelCasePropertyNameConverter constructor.
     */
    public function __construct()
    {
        $this->inflector = InflectorFactory::create()->build();
    }

    /**
     * Convert the specified JSON property name to its PHP property name.
     *
     * @param string $name
     *
     * @return string
     */
    public function convert(string $name): string
    {
        return $this->inflector->camelize($name);
    }
}
