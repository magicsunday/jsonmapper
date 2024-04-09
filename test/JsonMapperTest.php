<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace MagicSunday\Test;

use MagicSunday\Test\Classes\Base;
use MagicSunday\Test\Classes\ClassMap\CollectionSource;
use MagicSunday\Test\Classes\ClassMap\CollectionTarget;
use MagicSunday\Test\Classes\ClassMap\SourceItem;
use MagicSunday\Test\Classes\ClassMap\TargetItem;
use MagicSunday\Test\Classes\Collection;
use MagicSunday\Test\Classes\CustomClass;
use MagicSunday\Test\Classes\CustomConstructor;
use MagicSunday\Test\Classes\Initialized;
use MagicSunday\Test\Classes\MapPlainArrayKeyValueClass;
use MagicSunday\Test\Classes\MultidimensionalArray;
use MagicSunday\Test\Classes\Person;
use MagicSunday\Test\Classes\PlainArrayClass;
use MagicSunday\Test\Classes\Simple;
use MagicSunday\Test\Classes\VariadicSetterClass;
use MagicSunday\Test\Classes\VipPerson;
use MagicSunday\Test\Provider\DataProvider;
use stdClass;

/**
 * Class JsonMapperTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 *
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class JsonMapperTest extends TestCase
{
    /**
     * @return string[][]
     */
    public static function mapArrayOrCollectionWithIntegerKeysJsonDataProvider()
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
     *
     * @test
     *
     * @param string $jsonString
     */
    public function mapArrayOrCollection($jsonString)
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray($jsonString),
                Base::class,
                Collection::class
            );

        self::assertInstanceOf(Collection::class, $result);
        self::assertContainsOnlyInstancesOf(Base::class, $result);
        self::assertSame('Item 1', $result[0]->name);
        self::assertSame('Item 2', $result[1]->name);
    }

    /**
     * Tests mapping an array or collection of objects.
     *
     * @test
     */
    public function mapArrayOrCollectionWithStringKeys()
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray(
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
    public static function mapSimpleArrayJsonDataProvider()
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
    public function mapSimpleArray($jsonString)
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray($jsonString),
                Base::class
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertInternalType('array', $result->simpleArray);
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
    public static function mapSimpleCollectionJsonDataProvider()
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
    public function mapSimpleCollection($jsonString)
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray($jsonString),
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
    public static function mapCustomTypeJsonDataProvider()
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
    public function mapCustomType($jsonString)
    {
        $result = $this->getJsonMapper()
            ->addType(
                CustomConstructor::class,
                static function ($value) {
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
                $this->getJsonAsArray($jsonString),
                Base::class
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertInstanceOf(CustomConstructor::class, $result->customContructor);
        self::assertSame('John Doe', $result->customContructor->name);
    }

    /**
     * @return string[][]
     */
    public static function mapSimpleTypesJsonDataProvider()
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
    public function mapSimpleTypesJson($jsonString)
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray($jsonString),
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
    public static function mapObjectUsingCustomClassNameJsonDataProvider()
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
    public function mapObjectUsingCustomClassName($jsonString)
    {
        $result = $this->getJsonMapper()
            ->addCustomClassMapEntry(
                Person::class,
                // Map each entry of the collection to a separate class
                static function ($value) {
                    if ((is_array($value) && $value['is_vip']) || (($value instanceof stdClass) && $value->is_vip)) {
                        return VipPerson::class;
                    }

                    return Person::class;
                }
            )
            ->map(
                $this->getJsonAsArray($jsonString),
                Base::class
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertInstanceOf(CustomClass::class, $result->customClass);
        self::assertInternalType('array', $result->customClass->persons);
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
    public function mapEmptyObject()
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray(<<<JSON
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
    public function mapToPrivateProperty()
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray(<<<JSON
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
    public function checkCamelCasePropertyConverter()
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray(<<<JSON
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
    public function mapArrayOfObjects()
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray(<<<JSON
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

        self::assertInternalType('array', $result);
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
    public function mapSingleObjectWithGivenCollection()
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray(<<<JSON
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

    /**
     * Tests mapping of a multidimensional array.
     *
     * @test
     */
    public function mapArrayOfArray()
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray(<<<JSON
{
    "persons": [
        [
            {
                "name": "John Doe 1"
            },
            {
                "name": "Jane Doe 1"
            }
        ],
        [
            {
                "name": "John Doe 2"
            },
            {
                "name": "Jane Doe 2"
            }
        ]
    ]
}
JSON
                ),
                MultidimensionalArray::class
            );

        self::assertInstanceOf(MultidimensionalArray::class, $result);
        self::assertInternalType('array', $result->persons);
        self::assertContainsOnly('array', $result->persons);
        self::assertContainsOnlyInstancesOf(Person::class, $result->persons[0]);
        self::assertContainsOnlyInstancesOf(Person::class, $result->persons[1]);
    }

    /**
     * Tests mapping of values with an initial value.
     *
     * @test
     */
    public function mapInitialized()
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray('{}'),
                Initialized::class
            );

        self::assertInstanceOf(Initialized::class, $result);
        self::assertSame(10, $result->integer);
        self::assertSame([], $result->array);
        self::assertFalse($result->bool);
    }

    /**
     * Tests mapping of default values using @MagicSunday\JsonMapper\Annotation\ReplaceNullWithDefaultValue
     * annotation in case JSON contains NULL.
     *
     * @test
     */
    public function mapNullToDefaultValueUsingAnnotation()
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray(
                    '
{
    "integer": null,
    "bool": null,
    "array": null
}
'),
                Initialized::class
            );

        self::assertInstanceOf(Initialized::class, $result);
        self::assertSame(10, $result->integer);
        self::assertSame([], $result->array);
        self::assertFalse($result->bool);
    }

    /**
     * @return string[][]
     */
    public static function mapPlainArrayJsonDataProvider()
    {
        return [
            'mapPlainArray' => [
                DataProvider::mapPlainArrayJson(),
            ],
        ];
    }

    /**
     * @dataProvider mapPlainArrayJsonDataProvider
     *
     * @param string $jsonString
     *
     * @test
     */
    public function mapPlainArray($jsonString)
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray($jsonString)
            );

        self::assertInternalType('array', $result);
        self::assertCount(26, $result);

        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsObject($jsonString)
            );

        self::assertInternalType('array', $result);
        self::assertCount(26, $result);
    }

    /**
     * @return string[][]
     */
    public static function mapPlainArrayKeyValueJsonDataProvider()
    {
        return [
            'mapPlainArrayKeyValue' => [
                DataProvider::mapPlainArrayKeyValueJson(),
            ],
        ];
    }

    /**
     * @dataProvider mapPlainArrayKeyValueJsonDataProvider
     *
     * @param string $jsonString
     *
     * @test
     */
    public function mapPlainArrayKeyValue($jsonString)
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray($jsonString)
            );

        self::assertInternalType('array', $result);
        self::assertCount(26, $result);
        self::assertArrayHasKey('A', $result);
        self::assertSame(1, $result['A']);
        self::assertArrayHasKey('Z', $result);
        self::assertSame(26, $result['Z']);

        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsObject($jsonString)
            );

        self::assertInternalType('object', $result);
        self::assertInstanceOf(stdClass::class, $result);
        self::assertObjectHasAttribute('A', $result);
        self::assertSame(1, $result->A);
        self::assertObjectHasAttribute('Z', $result);
        self::assertSame(26, $result->Z);

        // Map plain array with key <=> value pair to a custom class
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsObject($jsonString),
                MapPlainArrayKeyValueClass::class
            );

        self::assertInternalType('object', $result);
        self::assertInstanceOf(MapPlainArrayKeyValueClass::class, $result);
        self::assertObjectHasAttribute('a', $result);
        self::assertSame(1, $result->a);
        self::assertObjectHasAttribute('z', $result);
        self::assertSame(26, $result->z);
    }

    /**
     * Tests settings a class property using a variadic setter method.
     *
     * @test
     */
    public function variadicSetter()
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsObject(
                    <<<JSON
{
    "values": [
        1,
        2,
        3,
        4,
        5
    ]
}
JSON
                ),
                VariadicSetterClass::class
            );

        self::assertEquals([1, 2, 3, 4, 5], $result->getValues());
    }

    /**
     * Tests settings a plain array.
     *
     * @test
     */
    public function plainArrayClass()
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsObject(
                    <<<JSON
{
    "values": [
        1,
        2,
        3,
        4,
        5
    ]
}
JSON
                ),
                PlainArrayClass::class
            );

        self::assertEquals([1, 2, 3, 4, 5], $result->getValues());
    }

    /**
     * Tests mapping an object to a custom class using a class map entry.
     *
     * @test
     */
    public function mappingBaseElementUsingClassMap()
    {
        $result = $this->getJsonMapper([
            SourceItem::class => TargetItem::class,
        ])
            ->map(
                $this->getJsonAsObject(
                    <<<JSON
{
    "item": {}
}
JSON
                ),
                SourceItem::class
            );

        self::assertInstanceOf(TargetItem::class, $result);
    }

    /**
     * Tests mapping a collection of objects to a custom class using a class map entry.
     *
     * @test
     */
    public function mappingCollectionElementsUsingClassMap()
    {
        $result = $this->getJsonMapper([
            SourceItem::class       => TargetItem::class,
            CollectionSource::class => CollectionTarget::class,
        ])
            ->map(
                $this->getJsonAsObject(
                    <<<JSON
[
    {
        "item": {}
    },
    {
        "item": {}
    },
    {
        "item": {}
    }
]
JSON
                ),
                SourceItem::class,
                CollectionSource::class
            );

        self::assertInstanceOf(CollectionTarget::class, $result);
        self::assertContainsOnlyInstancesOf(TargetItem::class, $result);
    }
}
