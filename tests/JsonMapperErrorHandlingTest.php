<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test;

use InvalidArgumentException;
use MagicSunday\Test\Classes\Base;
use MagicSunday\Test\Classes\Collection;
use PHPUnit\Framework\Attributes\Test;

/**
 * Class JsonMapperErrorHandlingTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class JsonMapperErrorHandlingTest extends TestCase
{
    /**
     * Tests that an exception is thrown when the class does not exist.
     */
    #[Test]
    public function throwsExceptionWhenClassDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class [NonExistentClass] does not exist');

        $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray('{"name": "test"}'),
                'NonExistentClass'
            );
    }

    /**
     * Tests that an exception is thrown when the collection class does not exist.
     */
    #[Test]
    public function throwsExceptionWhenCollectionClassDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class [NonExistentCollection] does not exist');

        $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray('[{"name": "test"}]'),
                Base::class,
                'NonExistentCollection'
            );
    }

    /**
     * Tests mapping with null as class name returns the JSON as is.
     */
    #[Test]
    public function returnsJsonWhenClassNameIsNull(): void
    {
        $json = $this->getJsonAsArray('{"name": "test", "value": 123}');
        
        $result = $this->getJsonMapper()->map($json, null);

        self::assertSame($json, $result);
    }

    /**
     * Tests that addType returns the JsonMapper instance for method chaining.
     */
    #[Test]
    public function addTypeReturnsJsonMapperForChaining(): void
    {
        $mapper = $this->getJsonMapper();
        
        $result = $mapper->addType(
            \DateTime::class,
            static function ($value): ?\DateTime {
                return $value ? new \DateTime($value) : null;
            }
        );

        self::assertSame($mapper, $result);
    }

    /**
     * Tests that addCustomClassMapEntry returns the JsonMapper instance for method chaining.
     */
    #[Test]
    public function addCustomClassMapEntryReturnsJsonMapperForChaining(): void
    {
        $mapper = $this->getJsonMapper();
        
        $result = $mapper->addCustomClassMapEntry(
            Base::class,
            static function ($value): string {
                return Base::class;
            }
        );

        self::assertSame($mapper, $result);
    }

    /**
     * Tests mapping with scalar JSON value (not array or object).
     */
    #[Test]
    public function mapsScalarJsonValue(): void
    {
        $result = $this->getJsonMapper()
            ->map(
                'simple string',
                Base::class
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertNull($result->name);
    }

    /**
     * Tests mapping with numeric JSON value.
     */
    #[Test]
    public function mapsNumericJsonValue(): void
    {
        $result = $this->getJsonMapper()
            ->map(
                123,
                Base::class
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertNull($result->name);
    }

    /**
     * Tests mapping empty array without class name returns empty array.
     */
    #[Test]
    public function mapsEmptyArrayWithoutClassName(): void
    {
        $result = $this->getJsonMapper()->map([]);

        self::assertSame([], $result);
    }

    /**
     * Tests mapping with class map entry using closure.
     */
    #[Test]
    public function mapsWithClassMapClosure(): void
    {
        $result = $this->getJsonMapper([
            Base::class => static function ($json): string {
                return Base::class;
            }
        ])->map(
            $this->getJsonAsArray('{"name": "test"}'),
            Base::class
        );

        self::assertInstanceOf(Base::class, $result);
        self::assertSame('test', $result->name);
    }

    /**
     * Tests that properties not in the class are ignored.
     */
    #[Test]
    public function ignoresUndefinedProperties(): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray(
                    <<<JSON
{
    "name": "test",
    "undefinedProperty": "should be ignored",
    "anotherUndefined": 123
}
JSON
                ),
                Base::class
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertSame('test', $result->name);
        self::assertObjectNotHasProperty('undefinedProperty', $result);
        self::assertObjectNotHasProperty('anotherUndefined', $result);
    }
}
