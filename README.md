[![Latest version](https://img.shields.io/github/v/release/magicsunday/jsonmapper?sort=semver)](https://github.com/magicsunday/jsonmapper/releases/latest)
[![License](https://img.shields.io/github/license/magicsunday/jsonmapper)](https://github.com/magicsunday/jsonmapper/blob/main/LICENSE)
[![CI](https://github.com/magicsunday/jsonmapper/actions/workflows/ci.yml/badge.svg)](https://github.com/magicsunday/jsonmapper/actions/workflows/ci.yml)

# JsonMapper
This module provides a mapper to map JSON to PHP classes utilizing Symfony's property info and access packages.

## Installation

### Using Composer
To install using [composer](https://getcomposer.org/), just run the following command from the command line.

```bash
composer require magicsunday/jsonmapper
```

To remove the module run:
```bash
composer remove magicsunday/jsonmapper
```


## Usage
### PHP classes
In order to guarantee a seamless mapping of a JSON response into PHP classes you should prepare your classes well.
Annotate all properties with the requested type.

In order to ensure correct mapping of a collection, the property has to be annotated using
the phpDocumentor collection annotation type. A collection is a non-scalar value capable of containing other
values.

For example:

```php
@var SomeCollection<DateTime>
@var SomeCollection<string>
@var Collection\SomeCollection<App\Entity\SomeEntity>
```


#### Custom attributes
Sometimes its may be required to circumvent the limitations of a poorly designed API. Together with custom
attributes it becomes possible to fix some API design issues (e.g. mismatch between documentation and webservice
response), to create a clean SDK.

##### #[MagicSunday\JsonMapper\Attribute\ReplaceNullWithDefaultValue]
This attribute is used to inform the JsonMapper that an existing default value should be used when
setting a property, if the value derived from the JSON is a NULL value instead of the expected property type.

This can be necessary, for example, in the case of a bad API design, if the API documentation defines a
certain type (e.g. array), but the API call itself then returns NULL if no data is available for a property
instead of an empty array that can be expected.

```php
/**
 * @var array<string>
 */
#[MagicSunday\JsonMapper\Attribute\ReplaceNullWithDefaultValue]
public array $array = [];
```

If the mapping tries to assign NULL to the property, the default value will be used, as annotated.

##### #[MagicSunday\JsonMapper\Attribute\ReplaceProperty]
This attribute is used to inform the JsonMapper to replace one or more properties with another one. It's
used in class context.

For instance if you want to replace a cryptic named property to a more human-readable name.
```php
#[MagicSunday\JsonMapper\Attribute\ReplaceProperty('type', replaces: 'crypticTypeNameProperty')]
class FooClass
{
    /**
     * @var string
     */
    public $type;
}
```


### Instantiation

In order to create an instance of the JsonMapper you are required to pass some arguments to the constructor. The
constructor requires an instance of `\Symfony\Component\PropertyInfo\PropertyInfoExtractor` and an instance of
`\Symfony\Component\PropertyAccess\PropertyAccessor`. The other arguments are optional.

So first create instances of Symfony's property info extractors. Each list of extractors could contain any number of 
available extractors. You could also create your own extractors to adjust the process of extracting property info to 
your needs.

To use the `PhpDocExtractor` extractor you need to install the `phpdocumentor/reflection-docblock` library too.

```php
use \Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use \Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use \Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use \Symfony\Component\PropertyAccess\PropertyAccessor;
```

A common extractor setup:
```php
$listExtractors = [ new ReflectionExtractor() ];
$typeExtractors = [ new PhpDocExtractor() ];
$propertyInfoExtractor = new PropertyInfoExtractor($listExtractors, $typeExtractors);
```

Create an instance of the property accessor:
```php
$propertyAccessor = PropertyAccess::createPropertyAccessor();
```

Using the third argument you can pass a property name converter instance to the mapper. With this you can convert 
the JSON property names to you desired format your PHP classes are using.
```php
$nameConverter = new \MagicSunday\JsonMapper\Converter\CamelCasePropertyNameConverter();
```

The last constructor parameter allows you to pass a class map to JsonMapper in order to change the default mapping 
behaviour. For instance if you have an SDK which maps the JSON response of a webservice to PHP. Using the class map you could override
the default mapping to the SDK's classes by providing an alternative list of classes used to map.
```php
$classMap = [
    SdkFoo::class => Foo::class,
];
```

Create an instance of the JsonMapper:
```php
$mapper = new \MagicSunday\JsonMapper(
    $propertyInfoExtractor,
    $propertyAccessor,
    $nameConverter,
    $classMap
);
```

To handle custom or special types of objects, add them to the mapper. For instance to perform
special treatment if an object of type Bar should be mapped:
```php
$mapper->addType(
    Bar::class,
    /** @var mixed $value JSON data */
    static function ($value): ?Bar {
        return $value ? new Bar($value['name']) : null;
    }
);
```

or add a handler to map DateTime values:
```php
$mapper->addType(
    \DateTime::class,
    /** @var mixed $value JSON data */
    static function ($value): ?\DateTime {
        return $value ? new \DateTime($value) : null;
    }
);
```

Convert a JSON string into a JSON array/object using PHPs built in method `json_decode`
```php
$json = json_decode('JSON STRING', true, 512, JSON_THROW_ON_ERROR);
```

Call method `map` to do the actual mapping of the JSON object/array into PHP classes. Pass the initial class name
and optional the name of a collection class to the method.
```php
$mappedResult = $mapper->map($json, Foo::class, FooCollection::class);
```

A complete set-up may look like this:

```php
/**
 * Returns an instance of the JsonMapper for testing.
 *
 * @param string[]|Closure[] $classMap A class map to override the class names
 *
 * @return \MagicSunday\JsonMapper
 */
protected function getJsonMapper(array $classMap = []): \MagicSunday\JsonMapper
{
    $listExtractors = [ new ReflectionExtractor() ];
    $typeExtractors = [ new PhpDocExtractor() ];
    $extractor      = new PropertyInfoExtractor($listExtractors, $typeExtractors);

    return new \MagicSunday\JsonMapper(
        $extractor,
        PropertyAccess::createPropertyAccessor(),
        new CamelCasePropertyNameConverter(),
        $classMap
    );
}
```

## Development

### Testing
```bash
composer update
composer ci:cgl
composer ci:test
composer ci:test:php:phplint
composer ci:test:php:phpstan
composer ci:test:php:rector
composer ci:test:php:cpd
composer ci:test:php:unit
```
