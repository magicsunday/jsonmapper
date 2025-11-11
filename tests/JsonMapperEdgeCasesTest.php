<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test;

use MagicSunday\Test\Classes\Base;
use MagicSunday\Test\Classes\Collection;
use MagicSunday\Test\Classes\Person;
use MagicSunday\Test\Classes\Simple;
use PHPUnit\Framework\Attributes\Test;

/**
 * Class JsonMapperEdgeCasesTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class JsonMapperEdgeCasesTest extends TestCase
{
    /**
     * Tests mapping with property name converter disabled (null converter).
     */
    #[Test]
    public function mapsWithoutPropertyNameConverter(): void
    {
        $listExtractors = [new \Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor()];
        $typeExtractors = [new \Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor()];
        $extractor = new \Symfony\Component\PropertyInfo\PropertyInfoExtractor($listExtractors, $typeExtractors);

        $mapper = new \MagicSunday\JsonMapper(
            $extractor,
            \Symfony\Component\PropertyAccess\PropertyAccess::createPropertyAccessor(),
            null, // No name converter
            []
        );

        $result = $mapper->map(
            $this->getJsonAsArray('{"name": "test"}'),
            Base::class
        );

        self::assertInstanceOf(Base::class, $result);
        self::assertSame('test', $result->name);
    }

    /**
     * Tests mapping empty collection.
     */
    #[Test]
    public function mapsEmptyCollection(): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray('[]'),
                Base::class,
                Collection::class
            );

        self::assertInstanceOf(Collection::class, $result);
        self::assertCount(0, $result);
    }

    /**
     * Tests mapping collection with null elements.
     */
    #[Test]
    public function mapsCollectionWithNullElements(): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray('[null, null]'),
                Base::class,
                Collection::class
            );

        self::assertInstanceOf(Collection::class, $result);
        self::assertCount(2, $result);
        self::assertNull($result[0]);
        self::assertNull($result[1]);
    }

    /**
     * Tests mapping with mixed array of objects and non-objects.
     */
    #[Test]
    public function handlesNonIterableWithArraysOrObjects(): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray('{"name": "value", "value": 123}'),
                Base::class
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertSame('value', $result->name);
    }

    /**
     * Tests mapping array as property value.
     */
    #[Test]
    public function mapsArrayPropertyValue(): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray(
                    <<<JSON
{
    "simpleArray": [
        {"id": 1, "name": "First"},
        {"id": 2, "name": "Second"}
    ]
}
JSON
                ),
                Base::class
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertIsArray($result->simpleArray);
        self::assertCount(2, $result->simpleArray);
    }

    /**
     * Tests getCollectionValueType public method.
     */
    #[Test]
    public function getsCollectionValueType(): void
    {
        $mapper = $this->getJsonMapper();
        
        // Create a Type with collection value types
        $collectionValueType = new \Symfony\Component\PropertyInfo\Type(
            \Symfony\Component\PropertyInfo\Type::BUILTIN_TYPE_OBJECT,
            false,
            Simple::class
        );
        
        $type = new \Symfony\Component\PropertyInfo\Type(
            \Symfony\Component\PropertyInfo\Type::BUILTIN_TYPE_ARRAY,
            false,
            null,
            true,
            null,
            $collectionValueType
        );

        $result = $mapper->getCollectionValueType($type);

        self::assertInstanceOf(\Symfony\Component\PropertyInfo\Type::class, $result);
        self::assertSame(\Symfony\Component\PropertyInfo\Type::BUILTIN_TYPE_OBJECT, $result->getBuiltinType());
    }

    /**
     * Tests getCollectionValueType with no collection value types (returns default).
     */
    #[Test]
    public function getsDefaultCollectionValueTypeWhenNoneSet(): void
    {
        $mapper = $this->getJsonMapper();
        
        // Create a Type without collection value types
        $type = new \Symfony\Component\PropertyInfo\Type(
            \Symfony\Component\PropertyInfo\Type::BUILTIN_TYPE_ARRAY,
            false,
            null,
            true
        );

        $result = $mapper->getCollectionValueType($type);

        self::assertInstanceOf(\Symfony\Component\PropertyInfo\Type::class, $result);
        self::assertSame(\Symfony\Component\PropertyInfo\Type::BUILTIN_TYPE_STRING, $result->getBuiltinType());
    }

    /**
     * Tests mapping with custom type handler.
     */
    #[Test]
    public function mapsWithCustomTypeHandler(): void
    {
        $result = $this->getJsonMapper()
            ->addType(
                \DateTime::class,
                static function ($value): ?\DateTime {
                    return $value ? new \DateTime($value) : null;
                }
            )
            ->map(
                $this->getJsonAsArray(
                    <<<JSON
{
    "name": "Test Person",
    "birthDate": "2000-01-01"
}
JSON
                ),
                Person::class
            );

        self::assertInstanceOf(Person::class, $result);
        self::assertSame('Test Person', $result->name);
    }

    /**
     * Tests mapping object with integer property keys.
     */
    #[Test]
    public function detectsNumericIndexArray(): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray(
                    <<<JSON
[
    {"name": "Item 1"},
    {"name": "Item 2"},
    {"name": "Item 3"}
]
JSON
                ),
                Base::class
            );

        self::assertIsArray($result);
        self::assertCount(3, $result);
        self::assertContainsOnlyInstancesOf(Base::class, $result);
    }

    /**
     * Tests that collection is created even with string keys if it contains only arrays/objects.
     */
    #[Test]
    public function mapsIterableWithArraysOrObjects(): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray(
                    <<<JSON
{
    "a": {"name": "Item A"},
    "b": {"name": "Item B"}
}
JSON
                ),
                Base::class,
                Collection::class
            );

        self::assertInstanceOf(Collection::class, $result);
        self::assertCount(2, $result);
    }
}
