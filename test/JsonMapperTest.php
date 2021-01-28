<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test;

use JsonException;
use MagicSunday\JsonMapper;
use MagicSunday\Test\Classes\Bar;
use MagicSunday\Test\Classes\Collection;
use MagicSunday\Test\Classes\Foo;
use MagicSunday\Test\Provider\DataProvider;
use PHPUnit\Framework\TestCase;
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
class JsonMapperTest extends TestCase
{
    /**
     * Returns an instance of the JsonMapper for testing.
     *
     * @param array $classMap
     *
     * @return JsonMapper
     */
    private function getJsonMapper(array $classMap = []): JsonMapper
    {
        $listExtractors = [ new ReflectionExtractor() ];
        $typeExtractors = [ new PhpDocExtractor() ];
        $extractor      = new PropertyInfoExtractor($listExtractors, $typeExtractors);

        return new JsonMapper(
            $extractor,
            PropertyAccess::createPropertyAccessor(),
            $classMap
        );
    }

    /**
     * Returns the decoded JSON array.
     *
     * @param string $jsonString
     *
     * @return array
     * @throws JsonException
     */
    private function getJsonArray(string $jsonString): array
    {
        return json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return string[][]
     */
    public function mapArrayJsonDataProvider(): array
    {
        return [
            'mapArray' => [
                DataProvider::mapArrayJson(),
            ],
        ];
    }

    /**
     * Tests mapping an array of objects.
     *
     * @dataProvider mapArrayJsonDataProvider
     *
     * @test
     *
     * @param string $jsonString
     */
    public function mapArray(string $jsonString)
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonArray($jsonString),
                Foo::class,
                Collection::class
            );

        self::assertInstanceOf(Collection::class, $result);
        self::assertContainsOnlyInstancesOf(Foo::class, $result);
        self::assertSame('Foo 1', $result[0]->name);
        self::assertSame('Foo 2', $result[1]->name);
    }

    /**
     * @return string[][]
     */
    public function mapCollectionJsonDataProvider(): array
    {
        return [
            'mapCollection' => [
                DataProvider::mapCollectionJson(),
            ],
        ];
    }

    /**
     * Tests mapping an array of objects.
     *
     * @dataProvider mapCollectionJsonDataProvider
     *
     * @test
     *
     * @param string $jsonString
     */
    public function mapCollection(string $jsonString)
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonArray($jsonString),
                Foo::class,
                Collection::class
            );

        self::assertInstanceOf(Collection::class, $result);
        self::assertContainsOnlyInstancesOf(Foo::class, $result);
        self::assertSame('Foo 1', $result[0]->name);
        self::assertSame('Foo 2', $result[1]->name);
    }

    /**
     * @return string[][]
     */
    public function mapCustomTypeJsonDataProvider(): array
    {
        return [
            'mapCustomType' => [
                DataProvider::mapCustomTypeJson(),
            ],
        ];
    }

    /**
     * Tests mapping a value using a custom type mapper closure.
     *
     * @dataProvider mapCustomTypeJsonDataProvider
     *
     * @test
     *
     * @param string $jsonString
     */
    public function mapCustomType(string $jsonString)
    {
        $result = $this->getJsonMapper()
            ->addType(
                Bar::class,
                static function ($value): ?Bar {
                    return $value ? new Bar($value['name']) : null;
                }
            )
            ->map(
                $this->getJsonArray($jsonString),
                Foo::class
            );

        self::assertInstanceOf(Foo::class, $result);
        self::assertInstanceOf(Bar::class, $result->bar);
        self::assertSame('Foo bar', $result->bar->name);
    }
}
