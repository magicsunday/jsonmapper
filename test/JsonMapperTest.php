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
use MagicSunday\Test\Classes\Base;
use MagicSunday\Test\Classes\Collection;
use MagicSunday\Test\Classes\CustomConstructor;
use MagicSunday\Test\Classes\Simple;
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
        $typeExtractors = [ new ReflectionExtractor(), new PhpDocExtractor() ];
        $extractor      = new PropertyInfoExtractor($listExtractors, $typeExtractors);

        return new JsonMapper(
            $extractor,
            PropertyAccess::createPropertyAccessor(),
            $classMap
        );
    }

    /**
     * Returns the decoded JSON as array.
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
        /** @var Collection $result */
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonArray($jsonString),
                Base::class,
                Collection::class
            );

        self::assertInstanceOf(Collection::class, $result);
        self::assertContainsOnlyInstancesOf(Base::class, $result);
        self::assertSame('Item 1', $result[0]->name);
        self::assertSame('Item 2', $result[1]->name);
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
        /** @var Collection $result */
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonArray($jsonString),
                Base::class,
                Collection::class
            );

        self::assertInstanceOf(Collection::class, $result);
        self::assertContainsOnlyInstancesOf(Base::class, $result);
        self::assertSame('Item 1', $result[0]->name);
        self::assertSame('Item 2', $result[1]->name);
    }

    /**
     * @return string[][]
     */
    public function mapSimpleArrayJsonDataProvider(): array
    {
        return [
            'mapSimpleArray' => [
                DataProvider::mapSimpleArrayJson(),
            ],
        ];
    }

    /**
     * Tests mapping an array of objects to a property.
     *
     * @dataProvider mapSimpleArrayJsonDataProvider
     *
     * @test
     *
     * @param string $jsonString
     */
    public function mapSimpleArray(string $jsonString)
    {
        /** @var Base $result */
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonArray($jsonString),
                Base::class
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertIsArray($result->simpleArray);
        self::assertCount(2, $result->simpleArray);
        self::assertContainsOnlyInstancesOf(Simple::class, $result->simpleArray);
        self::assertSame(1, $result->simpleArray[0]->id);
        self::assertSame('Item 1', $result->simpleArray[0]->name);
        self::assertSame(2, $result->simpleArray[1]->id);
        self::assertSame('Item 2', $result->simpleArray[1]->name);
    }

    /**
     * @return string[][]
     */
    public function mapSimpleCollectionJsonDataProvider(): array
    {
        return [
            'mapSimpleCollection' => [
                DataProvider::mapSimpleCollectionJson(),
            ],
        ];
    }

    /**
     * Tests mapping a collection of objects to a property.
     *
     * @dataProvider mapSimpleCollectionJsonDataProvider
     *
     * @test
     *
     * @param string $jsonString
     */
    public function mapSimpleCollection(string $jsonString)
    {
        /** @var Base $result */
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonArray($jsonString),
                Base::class
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertInstanceOf(Collection::class, $result->simpleCollection);
        self::assertCount(2, $result->simpleCollection);
        self::assertContainsOnlyInstancesOf(Simple::class, $result->simpleCollection);
        self::assertSame(1, $result->simpleCollection[0]->id);
        self::assertSame('Item 1', $result->simpleCollection[0]->name);
        self::assertSame(2, $result->simpleCollection[1]->id);
        self::assertSame('Item 2', $result->simpleCollection[1]->name);
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
        /** @var Base $result */
        $result = $this->getJsonMapper()
            ->addType(
                CustomConstructor::class,
                static function ($value): ?CustomConstructor {
                    return $value ? new CustomConstructor($value['name']) : null;
                }
            )
            ->map(
                $this->getJsonArray($jsonString),
                Base::class
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertInstanceOf(CustomConstructor::class, $result->customContructor);
        self::assertSame('John Doe', $result->customContructor->name);
    }

    /**
     * @return string[][]
     */
    public function mapSimpleTypesJsonDataProvider(): array
    {
        return [
            'mapCustomType' => [
                DataProvider::mapSimpleTypesJson(),
            ],
        ];
    }

    /**
     * Tests mapping simple types.
     *
     * @dataProvider mapSimpleTypesJsonDataProvider
     *
     * @test
     *
     * @param string $jsonString
     */
    public function mapSimpleTypesJson(string $jsonString)
    {
        /** @var Collection $result */
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonArray($jsonString),
                Simple::class,
                Collection::class
            );

        self::assertInstanceOf(Collection::class, $result);
        self::assertContainsOnlyInstancesOf(Simple::class, $result);

        self::assertSame(123, $result[0]->int);
        self::assertSame(123.45, $result[0]->float);
        self::assertTrue($result[0]->bool);
        self::assertSame('string', $result[0]->string);

        self::assertSame(0, $result[1]->int);
        self::assertSame(0.0, $result[1]->float);
        self::assertFalse($result[1]->bool);
        self::assertSame('', $result[1]->string);
    }
}
