<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test;

use DateInterval;
use MagicSunday\JsonMapper\Configuration\JsonMapperConfig;
use MagicSunday\JsonMapper\Configuration\MappingConfiguration;
use MagicSunday\JsonMapper\Exception\UnknownPropertyException;
use MagicSunday\JsonMapper\Value\ClosureTypeHandler;
use MagicSunday\Test\Classes\Base;
use MagicSunday\Test\Classes\BaseCollection;
use MagicSunday\Test\Classes\ClassMap\CollectionSource;
use MagicSunday\Test\Classes\ClassMap\CollectionTarget;
use MagicSunday\Test\Classes\ClassMap\SourceItem;
use MagicSunday\Test\Classes\ClassMap\TargetItem;
use MagicSunday\Test\Classes\Collection;
use MagicSunday\Test\Classes\CustomConstructor;
use MagicSunday\Test\Classes\DateTimeHolder;
use MagicSunday\Test\Classes\EnumHolder;
use MagicSunday\Test\Classes\Initialized;
use MagicSunday\Test\Classes\MapPlainArrayKeyValueClass;
use MagicSunday\Test\Classes\MultidimensionalArray;
use MagicSunday\Test\Classes\NullableStringHolder;
use MagicSunday\Test\Classes\Person;
use MagicSunday\Test\Classes\PlainArrayClass;
use MagicSunday\Test\Classes\ScalarHolder;
use MagicSunday\Test\Classes\Simple;
use MagicSunday\Test\Classes\UnionHolder;
use MagicSunday\Test\Classes\VariadicSetterClass;
use MagicSunday\Test\Classes\VipPerson;
use MagicSunday\Test\Fixtures\Enum\SampleStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

use function is_array;
use function is_string;

/**
 * Class JsonMapperTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class JsonMapperTest extends TestCase
{
    /**
     * @return string[][]
     */
    public static function mapArrayOrCollectionWithIntegerKeysJsonDataProvider(): array
    {
        return [
            'mapArray' => [
                Provider\DataProvider::mapArrayJson(),
            ],
            'mapCollection' => [
                Provider\DataProvider::mapCollectionJson(),
            ],
        ];
    }

    /**
     * Tests mapping an array or collection of objects.
     *
     * @param string $jsonString
     */
    #[Test]
    #[DataProvider('mapArrayOrCollectionWithIntegerKeysJsonDataProvider')]
    public function mapArrayOrCollection(string $jsonString): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray($jsonString),
                Base::class,
                Collection::class
            );

        self::assertInstanceOf(Collection::class, $result);
        self::assertContainsOnlyInstancesOf(Base::class, $result);
        self::assertInstanceOf(Base::class, $result[0]);
        self::assertInstanceOf(Base::class, $result[1]);
        self::assertSame('Item 1', $result[0]->name);
        self::assertSame('Item 2', $result[1]->name);
    }

    /**
     * Tests mapping a collection using a generic @extends annotation.
     */
    #[Test]
    public function mapCollectionUsingDocBlockExtends(): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray(Provider\DataProvider::mapCollectionJson()),
                null,
                BaseCollection::class
            );

        self::assertInstanceOf(BaseCollection::class, $result);
        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(Base::class, $result);
        self::assertInstanceOf(Base::class, $result[0]);
        self::assertSame('Item 1', $result[0]->name);
        self::assertInstanceOf(Base::class, $result[1]);
        self::assertSame('Item 2', $result[1]->name);
    }

    /**
     * Tests mapping an array or collection of objects.
     */
    #[Test]
    public function mapArrayOrCollectionWithStringKeys(): void
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
        self::assertInstanceOf(Base::class, $iterator->current());
        self::assertSame('Item 1', $iterator->current()->name);

        $iterator->next();

        self::assertSame('bar', $iterator->key());
        self::assertInstanceOf(Base::class, $iterator->current());
        self::assertSame('Item 2', $iterator->current()->name);
    }

    /**
     * @return string[][]
     */
    public static function mapSimpleArrayJsonDataProvider(): array
    {
        return [
            'mapSimpleArray' => [
                Provider\DataProvider::mapSimpleArrayJson(),
            ],
        ];
    }

    /**
     * Tests mapping an array of objects to a property.
     *
     * @param string $jsonString
     */
    #[Test]
    #[DataProvider('mapSimpleArrayJsonDataProvider')]
    public function mapSimpleArray(string $jsonString): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray($jsonString),
                Base::class
            );

        self::assertInstanceOf(Base::class, $result);
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
    public static function mapSimpleCollectionJsonDataProvider(): array
    {
        return [
            'mapSimpleCollection' => [
                Provider\DataProvider::mapSimpleCollectionJson(),
            ],
        ];
    }

    /**
     * Tests mapping a collection of objects to a property.
     *
     * @param string $jsonString
     */
    #[Test]
    #[DataProvider('mapSimpleCollectionJsonDataProvider')]
    public function mapSimpleCollection(string $jsonString): void
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
        self::assertInstanceOf(Simple::class, $result->simpleCollection[0]);
        self::assertSame(1, $result->simpleCollection[0]->id);
        self::assertSame('Item 1', $result->simpleCollection[0]->name);
        self::assertInstanceOf(Simple::class, $result->simpleCollection[1]);
        self::assertSame(2, $result->simpleCollection[1]->id);
        self::assertSame('Item 2', $result->simpleCollection[1]->name);
    }

    /**
     * @return string[][]
     */
    public static function mapCustomTypeJsonDataProvider(): array
    {
        return [
            'mapCustomType' => [
                Provider\DataProvider::mapCustomTypeJson(),
            ],
        ];
    }

    /**
     * Tests mapping a value using a custom type mapper closure.
     *
     * @param string $jsonString
     */
    #[Test]
    #[DataProvider('mapCustomTypeJsonDataProvider')]
    public function mapCustomType(string $jsonString): void
    {
        $result = $this->getJsonMapper()
            ->addTypeHandler(
                new ClosureTypeHandler(
                    CustomConstructor::class,
                    static function (mixed $value): ?CustomConstructor {
                        if (
                            is_array($value)
                            && isset($value['name'])
                            && is_string($value['name'])
                        ) {
                            return new CustomConstructor($value['name']);
                        }

                        if (
                            ($value instanceof stdClass)
                            && property_exists($value, 'name')
                            && is_string($value->name)
                        ) {
                            return new CustomConstructor($value->name);
                        }

                        return null;
                    },
                ),
            )
            ->map(
                $this->getJsonAsArray($jsonString),
                Base::class
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertSame('John Doe', $result->customContructor->name);
    }

    /**
     * @return string[][]
     */
    public static function mapSimpleTypesJsonDataProvider(): array
    {
        return [
            'mapCustomType' => [
                Provider\DataProvider::mapSimpleTypesJson(),
            ],
        ];
    }

    /**
     * Tests mapping simple types.
     *
     * @param string $jsonString
     */
    #[Test]
    #[DataProvider('mapSimpleTypesJsonDataProvider')]
    public function mapSimpleTypesJson(string $jsonString): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray($jsonString),
                Simple::class,
                Collection::class
            );

        self::assertInstanceOf(Collection::class, $result);
        self::assertContainsOnlyInstancesOf(Simple::class, $result);

        self::assertInstanceOf(Simple::class, $result[0]);
        self::assertSame(123, $result[0]->int);
        self::assertSame(123.45, $result[0]->float);
        self::assertTrue($result[0]->bool);
        self::assertSame('string', $result[0]->string);

        self::assertInstanceOf(Simple::class, $result[1]);
        self::assertSame(0, $result[1]->int);
        self::assertSame(0.0, $result[1]->float);
        self::assertFalse($result[1]->bool);
        self::assertSame('', $result[1]->string);
        self::assertNull($result[1]->empty);
    }

    /**
     * @return string[][]
     */
    public static function mapObjectUsingCustomClassNameJsonDataProvider(): array
    {
        return [
            'mapCustomClassName' => [
                Provider\DataProvider::mapCustomClassNameJson(),
            ],
        ];
    }

    /**
     * Tests mapping an object using a custom class name provider closure.
     *
     * @param string $jsonString
     */
    #[Test]
    #[DataProvider('mapObjectUsingCustomClassNameJsonDataProvider')]
    public function mapObjectUsingCustomClassName(string $jsonString): void
    {
        $result = $this->getJsonMapper()
            ->addCustomClassMapEntry(
                Person::class,
                // Map each entry of the collection to a separate class
                static function (mixed $value): string {
                    if (
                        (
                            is_array($value)
                            && isset($value['is_vip'])
                            && ($value['is_vip'] === true)
                        )
                        || (
                            ($value instanceof stdClass)
                            && property_exists($value, 'is_vip')
                            && $value->is_vip
                        )
                    ) {
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
        self::assertCount(2, $result->customClass->persons);

        self::assertFalse($result->customClass->persons[0]->is_vip);
        self::assertSame('John Doe', $result->customClass->persons[0]->name);

        self::assertInstanceOf(VipPerson::class, $result->customClass->persons[1]);
        self::assertTrue($result->customClass->persons[1]->is_vip);
        self::assertSame(2, $result->customClass->persons[1]->oscars);
        self::assertSame('Jane Doe', $result->customClass->persons[1]->name);
    }

    /**
     * Tests mapping null to an object not failing.
     */
    #[Test]
    public function mapEmptyObject(): void
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
     */
    #[Test]
    public function mapToPrivateProperty(): void
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
     */
    #[Test]
    public function checkCamelCasePropertyConverter(): void
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
     */
    #[Test]
    public function mapArrayOfObjects(): void
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

        self::assertIsArray($result);
        self::assertContainsOnlyInstancesOf(Base::class, $result);
        self::assertSame('foo', $result[0]->name);
        self::assertSame('bar', $result[1]->name);
    }

    /**
     * Tests mapping a JSON object into an PHP object ignoring a given collection class as the
     * JSON does not contain a collection.
     */
    #[Test]
    public function mapSingleObjectWithGivenCollection(): void
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
     */
    #[Test]
    public function mapArrayOfArray(): void
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
        self::assertContainsOnlyInstancesOf(Person::class, $result->persons[0]);
        self::assertContainsOnlyInstancesOf(Person::class, $result->persons[1]);
    }

    /**
     * Tests mapping of values with an initial value.
     */
    #[Test]
    public function mapInitialized(): void
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
     * Tests mapping of default values using #[MagicSunday\JsonMapper\Attribute\ReplaceNullWithDefaultValue]
     * when the JSON payload contains null values.
     */
    #[Test]
    public function mapNullToDefaultValueUsingAttribute(): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray(<<<JSON
{
    "integer": null,
    "bool": null,
    "array": null
}
JSON),
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
    public static function mapPlainArrayJsonDataProvider(): array
    {
        return [
            'mapPlainArray' => [
                Provider\DataProvider::mapPlainArrayJson(),
            ],
        ];
    }

    /**
     * @param string $jsonString
     */
    #[Test]
    #[DataProvider('mapPlainArrayJsonDataProvider')]
    public function mapPlainArray(string $jsonString): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray($jsonString)
            );

        self::assertIsArray($result);
        self::assertCount(26, $result);

        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsObject($jsonString)
            );

        self::assertIsArray($result);
        self::assertCount(26, $result);
    }

    /**
     * @return string[][]
     */
    public static function mapPlainArrayKeyValueJsonDataProvider(): array
    {
        return [
            'mapPlainArrayKeyValue' => [
                Provider\DataProvider::mapPlainArrayKeyValueJson(),
            ],
        ];
    }

    /**
     * @param string $jsonString
     */
    #[Test]
    #[DataProvider('mapPlainArrayKeyValueJsonDataProvider')]
    public function mapPlainArrayKeyValue(string $jsonString): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray($jsonString)
            );

        self::assertIsArray($result);
        self::assertCount(26, $result);
        self::assertArrayHasKey('A', $result);
        self::assertSame(1, $result['A']);
        self::assertArrayHasKey('Z', $result);
        self::assertSame(26, $result['Z']);

        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsObject($jsonString)
            );

        self::assertIsObject($result);
        self::assertInstanceOf(stdClass::class, $result);
        self::assertObjectHasProperty('A', $result);
        self::assertSame(1, $result->A);
        self::assertObjectHasProperty('Z', $result);
        self::assertSame(26, $result->Z);

        // Map plain array with key <=> value pair to a custom class
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsObject($jsonString),
                MapPlainArrayKeyValueClass::class
            );

        self::assertIsObject($result);
        self::assertInstanceOf(MapPlainArrayKeyValueClass::class, $result);
        self::assertObjectHasProperty('a', $result);
        self::assertSame(1, $result->a);
        self::assertObjectHasProperty('z', $result);
        self::assertSame(26, $result->z);
    }

    /**
     * Tests settings a class property using a variadic setter method.
     */
    #[Test]
    public function variadicSetter(): void
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

        self::assertInstanceOf(VariadicSetterClass::class, $result);
        self::assertEquals([1, 2, 3, 4, 5], $result->getValues());
    }

    /**
     * Tests settings a plain array.
     */
    #[Test]
    public function plainArrayClass(): void
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

        self::assertInstanceOf(PlainArrayClass::class, $result);
        self::assertEquals([1, 2, 3, 4, 5], $result->getValues());
    }

    /**
     * Tests mapping an object to a custom class using a class map entry.
     */
    #[Test]
    public function mappingBaseElementUsingClassMap(): void
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
     */
    #[Test]
    public function mappingCollectionElementsUsingClassMap(): void
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

    #[Test]
    public function mapBackedEnumFromString(): void
    {
        $result = $this->getJsonMapper()
            ->map(['status' => 'active'], EnumHolder::class);

        self::assertInstanceOf(EnumHolder::class, $result);
        self::assertSame(SampleStatus::Active, $result->status);
    }

    #[Test]
    public function mapUnionTypeWithNumericString(): void
    {
        $result = $this->getJsonMapper()
            ->map([
                'value'    => '42',
                'fallback' => 'hello',
            ], UnionHolder::class);

        self::assertInstanceOf(UnionHolder::class, $result);
        self::assertSame(42, $result->value);
        self::assertSame('hello', $result->fallback);
    }

    #[Test]
    public function mapUnionTypeWithTextualValue(): void
    {
        $result = $this->getJsonMapper()
            ->map([
                'value'    => 'oops',
                'fallback' => 99,
            ], UnionHolder::class);

        self::assertInstanceOf(UnionHolder::class, $result);
        self::assertSame('oops', $result->value);
        self::assertSame(99, $result->fallback);
    }

    #[Test]
    public function mapDateTimeAndIntervalValues(): void
    {
        $result = $this->getJsonMapper()
            ->map([
                'createdAt' => '2024-04-01T12:00:00+00:00',
                'timeout'   => 'PT15M',
            ], DateTimeHolder::class);

        self::assertInstanceOf(DateTimeHolder::class, $result);
        self::assertSame('2024-04-01T12:00:00+00:00', $result->createdAt->format('c'));
        self::assertInstanceOf(DateInterval::class, $result->timeout);
        self::assertSame(15, $result->timeout->i);
    }

    #[Test]
    public function mapScalarShorthandValues(): void
    {
        $result = $this->getJsonMapper()
            ->map([
                'intValue'   => '42',
                'floatValue' => '3.14',
                'boolValue'  => '1',
            ], ScalarHolder::class);

        self::assertInstanceOf(ScalarHolder::class, $result);
        self::assertSame(42, $result->intValue);
        self::assertSame(3.14, $result->floatValue);
        self::assertTrue($result->boolValue);
    }

    #[Test]
    public function mapScalarZeroStringToFalse(): void
    {
        $result = $this->getJsonMapper()
            ->map([
                'intValue'   => '0',
                'floatValue' => '0',
                'boolValue'  => '0',
            ], ScalarHolder::class);

        self::assertInstanceOf(ScalarHolder::class, $result);
        self::assertSame(0, $result->intValue);
        self::assertSame(0.0, $result->floatValue);
        self::assertFalse($result->boolValue);
    }

    #[Test]
    public function mapEmptyStringToNullWhenEnabled(): void
    {
        $configuration = MappingConfiguration::lenient()->withEmptyStringAsNull(true);

        $result = $this->getJsonMapper()
            ->map(
                ['value' => ''],
                NullableStringHolder::class,
                null,
                null,
                $configuration,
            );

        self::assertInstanceOf(NullableStringHolder::class, $result);
        self::assertNull($result->value);
    }

    #[Test]
    public function itAppliesConfiguredStrictModeByDefault(): void
    {
        $config = (new JsonMapperConfig())->withStrictMode(true);

        $this->expectException(UnknownPropertyException::class);

        $this->getJsonMapper([], $config)->map(
            [
                'name'    => 'John Doe',
                'unknown' => 'value',
            ],
            Person::class,
        );
    }

    #[Test]
    public function itIgnoresUnknownPropertiesWhenConfigured(): void
    {
        $config = (new JsonMapperConfig())->withIgnoreUnknownProperties(true);

        $result = $this->getJsonMapper([], $config)
            ->mapWithReport(
                [
                    'name'    => 'John Doe',
                    'unknown' => 'value',
                ],
                Person::class,
            );

        self::assertInstanceOf(Person::class, $result->getValue());
        self::assertFalse($result->getReport()->hasErrors());
    }

    #[Test]
    public function itTreatsNullCollectionsAsEmptyWhenConfigured(): void
    {
        $config = (new JsonMapperConfig())->withTreatNullAsEmptyCollection(true);

        $result = $this->getJsonMapper([], $config)
            ->map(
                [
                    'simpleArray' => null,
                ],
                Base::class,
            );

        self::assertInstanceOf(Base::class, $result);
        self::assertSame([], $result->simpleArray);
    }

    #[Test]
    public function itUsesDefaultDateFormatFromConfiguration(): void
    {
        $config = (new JsonMapperConfig())->withDefaultDateFormat('d.m.Y H:i:s');

        $result = $this->getJsonMapper([], $config)
            ->map(
                [
                    'createdAt' => '24.01.2024 18:45:00',
                ],
                DateTimeHolder::class,
            );

        self::assertInstanceOf(DateTimeHolder::class, $result);
        self::assertSame('24.01.2024 18:45:00', $result->createdAt->format('d.m.Y H:i:s'));
    }

    #[Test]
    public function itAllowsScalarToObjectCastingWhenConfigured(): void
    {
        $config = (new JsonMapperConfig())->withScalarToObjectCasting(true);

        $result = $this->getJsonMapper([], $config)
            ->mapWithReport(
                [
                    'simple' => 'identifier',
                ],
                Base::class,
            );

        self::assertFalse($result->getReport()->hasErrors());
        $mapped = $result->getValue();
        self::assertInstanceOf(Base::class, $mapped);
    }
}
