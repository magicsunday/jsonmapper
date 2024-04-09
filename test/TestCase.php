<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace MagicSunday\Test;

use Closure;
use MagicSunday\JsonMapper;
use MagicSunday\JsonMapper\Converter\CamelCasePropertyNameConverter;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

/**
 * Class JsonMapperTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 *
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Returns an instance of the JsonMapper for testing.
     *
     * @param string[]|Closure[] $classMap
     *
     * @return JsonMapper
     */
    protected function getJsonMapper(array $classMap = [])
    {
        $listExtractors = [new ReflectionExtractor()];
        $typeExtractors = [new PhpDocExtractor()];
        $extractor      = new PropertyInfoExtractor($listExtractors, $typeExtractors);

        return new JsonMapper(
            $extractor,
            PropertyAccess::createPropertyAccessor(),
            new CamelCasePropertyNameConverter(),
            $classMap
        );
    }

    /**
     * Returns the decoded JSON as array.
     *
     * @param string $jsonString
     *
     * @return mixed|null
     */
    protected function getJsonAsArray($jsonString)
    {
        return json_decode($jsonString, true);
    }

    /**
     * Returns the decoded JSON as object.
     *
     * @param string $jsonString
     *
     * @return mixed|null
     */
    protected function getJsonAsObject($jsonString)
    {
        return json_decode($jsonString, false);
    }
}
