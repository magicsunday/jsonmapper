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
use MagicSunday\Test\Classes\CustomClass;
use MagicSunday\Test\Classes\CustomConstructor;
use MagicSunday\Test\Classes\Person;
use MagicSunday\Test\Classes\Simple;
use MagicSunday\Test\Classes\VipPerson;
use MagicSunday\Test\Provider\DataProvider;

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
     * Tests if an exception is thrown if the given class name did not exists.
     *
     * @test
     */
    public function checkThatNotExistingClassNameThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class [\This\Class\Does\Not\Exists] does not exist');

        $this->getJsonMapper()
            ->map(
                $this->getJsonArray(''),
                '\This\Class\Does\Not\Exists'
            );
    }

    /**
     * Tests if an exception is thrown if the given collection class name did not exists.
     *
     * @test
     */
    public function checkThatNotExistingCollectionClassNameThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class [\This\Collection\Class\Does\Not\Exists] does not exist');

        $this->getJsonMapper()
            ->map(
                $this->getJsonArray(''),
                Base::class,
                '\This\Collection\Class\Does\Not\Exists'
            );
    }

    /**
     * @return string[][]
     */
    public function mapArrayOrCollectionWithIntegerKeysJsonDataProvider(): array
    {
        return [
            'mapArray' => [
                DataProvider::mapArrayJson(),
            ],
            'mapCollection' => [
                DataProvider::mapCollectionJson(),
            ],
        ];
    }

    /**
     * Tests mapping an array or collection of objects.
     *
     * @dataProvider mapArrayOrCollectionWithIntegerKeysJsonDataProvider
     * @test
     *
     * @param string $jsonString
     */
    public function mapArrayOrCollection(string $jsonString): void
    {
        /** @var Collection<Base> $result */
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonArray($jsonString),
                Base::class,
                Collection::class
            );

        self::assertInstanceOf(Collection::class, $result);
        self::assertContainsOnlyInstancesOf(Base::class, $result);

        $iterator = $result->getIterator();

        $iterator->rewind();

        self::assertSame(0, $iterator->key());
        self::assertSame('Item 1', $iterator->current()->name);

        $iterator->next();

        self::assertSame(1, $iterator->key());
        self::assertSame('Item 2', $iterator->current()->name);
    }

    /**
     * Tests mapping an array or collection of objects.
     *
     * @test
     */
    public function mapArrayOrCollectionWithStringKeys(): void
    {
        /** @var Collection<Base> $result */
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonArray(
                    <<<JSON
{
  "foo": {
    "name": "Item 1"
  },
  "bar": {
    "name": "Item 2"
  }
}
JSON
                ),
                Base::class,
                Collection::class
            );

        self::assertInstanceOf(Collection::class, $result);
        self::assertContainsOnlyInstancesOf(Base::class, $result);

        $iterator = $result->getIterator();

        $iterator->rewind();

        self::assertSame('foo', $iterator->key());
        self::assertSame('Item 1', $iterator->current()->name);

        $iterator->next();

        self::assertSame('bar', $iterator->key());
        self::assertSame('Item 2', $iterator->current()->name);
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
    public function mapSimpleArray(string $jsonString): void
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
    public function mapSimpleCollection(string $jsonString): void
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
    public function mapCustomType(string $jsonString): void
    {
        /** @var Base $result */
        $result = $this->getJsonMapper()
            ->addType(
                CustomConstructor::class,
                static function ($value): ?CustomConstructor {
                    if (is_array($value) && $value['name']) {
                        return new CustomConstructor($value['name']);
                    }

                    if ($value->name) {
                        return new CustomConstructor($value->name);
                    }

                    return null;
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
    public function mapSimpleTypesJson(string $jsonString): void
    {
        /** @var Collection<Simple> $result */
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
        self::assertNull($result[1]->empty);
    }

    /**
     * @return string[][]
     */
    public function mapObjectUsingCustomClassNameJsonDataProvider(): array
    {
        return [
            'mapCustomClassName' => [
                DataProvider::mapCustomClassNameJson(),
            ],
        ];
    }

    /**
     * Tests mapping an object using a custom class name provider closure.
     *
     * @dataProvider mapObjectUsingCustomClassNameJsonDataProvider
     *
     * @test
     *
     * @param string $jsonString
     */
    public function mapObjectUsingCustomClassName(string $jsonString): void
    {
        /** @var Base $result */
        $result = $this->getJsonMapper()
            ->addCustomClassMapEntry(
                Person::class,
                // Map each entry of the collection to a separate class
                static function ($value): string {
                    if ((is_array($value) && $value['is_vip']) || (($value instanceof \stdClass) && $value->is_vip)) {
                        return VipPerson::class;
                    }

                    return Person::class;
                }
            )
            ->map(
                $this->getJsonArray($jsonString),
                Base::class
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertInstanceOf(CustomClass::class, $result->customClass);
        self::assertIsArray($result->customClass->persons);
        self::assertCount(2, $result->customClass->persons);

        self::assertInstanceOf(Person::class, $result->customClass->persons[0]);
        self::assertFalse($result->customClass->persons[0]->is_vip);
        self::assertSame('John Doe', $result->customClass->persons[0]->name);

        self::assertInstanceOf(VipPerson::class, $result->customClass->persons[1]);
        self::assertTrue($result->customClass->persons[1]->is_vip);
        self::assertSame(2, $result->customClass->persons[1]->oscars);
        self::assertSame('Jane Doe', $result->customClass->persons[1]->name);
    }

    /**
     * Tests mapping null to an object not failing.
     *
     * @test
     */
    public function mapEmptyObject(): void
    {
        /** @var Base $result */
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonArray(<<<JSON
{
    "simple": null
}
JSON
            ),
                Base::class
        );

        self::assertInstanceOf(Base::class, $result);
        self::assertNull($result->simple);
    }

    /**
     * Tests mapping a value to a private property using a setter method.
     *
     * @test
     */
    public function mapToPrivateProperty(): void
    {
        /** @var Base $result */
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonArray(<<<JSON
{
    "privateProperty": "Private property value"
}
JSON
                ),
                Base::class
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertSame('Private property value', $result->getPrivateProperty());
    }

    /**
     * Tests mapping json properties to camel case.
     *
     * @test
     */
    public function checkCamelCasePropertyConverter(): void
    {
        /** @var Base $result */
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonArray(<<<JSON
{
    "private_property": "Private property value"
}
JSON
                ),
                Base::class
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertSame('Private property value', $result->getPrivateProperty());
    }

    /**
     * Tests mapping a JSON array with objects into a plain PHP array with objects of given class.
     *
     * @test
     */
    public function mapArrayOfObjects(): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonArray(<<<JSON
[
    {
        "name": "foo"
    },
    {
        "name": "bar"
    }
]
JSON
                ),
                Base::class
            );

        self::assertIsArray($result);
        self::assertContainsOnlyInstancesOf(Base::class, $result);
        self::assertSame('foo', $result[0]->name);
        self::assertSame('bar', $result[1]->name);
    }

    /**
     * Tests mapping a JSON object into an PHP object ignoring a given collection class as the
     * JSON does not contain a collection.
     *
     * @test
     */
    public function mapSingleObjectWithGivenCollection(): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonArray(<<<JSON
{
    "name": "foo"
}
JSON
                ),
                Base::class,
                Collection::class
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertSame('foo', $result->name);
    }
}
