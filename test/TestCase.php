<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test;

use Closure;
use JsonException;
use MagicSunday\CamelCasePropertyNameConverter;
use MagicSunday\JsonMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

/**
 * Class JsonMapperTest
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
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
    protected function getJsonMapper(array $classMap = []): JsonMapper
    {
        $listExtractors = [ new ReflectionExtractor() ];
        $typeExtractors = [ new PhpDocExtractor() ];
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
     * @return object[]|object
     */
    protected function getJsonArray(string $jsonString)
    {
        try {
            return json_decode($jsonString, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return [];
        }
    }
}
