[![License: GPL v3](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)

[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat)](https://github.com/phpstan/phpstan)
[![PHP_CodeSniffer](https://img.shields.io/badge/PHP_CodeSniffer-PSR12-brightgreen.svg?style=flat)](https://github.com/squizlabs/PHP_CodeSniffer)
[![Unit tests](https://github.com/magicsunday/jsonmapper/actions/workflows/phpunit.yml/badge.svg)](https://github.com/magicsunday/jsonmapper/actions/workflows/phpunit.yml)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/magicsunday/jsonmapper/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/magicsunday/jsonmapper/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/magicsunday/jsonmapper/badges/build.png?b=master)](https://scrutinizer-ci.com/g/magicsunday/jsonmapper/build-status/master)
[![Code Coverage](https://scrutinizer-ci.com/g/magicsunday/jsonmapper/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/magicsunday/jsonmapper/?branch=master)
[![Code Climate](https://codeclimate.com/github/magicsunday/jsonmapper/badges/gpa.svg)](https://codeclimate.com/github/magicsunday/jsonmapper)

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


#### Custom annotations
Sometimes its may be required to circumvent the limitations of a poorly designed API. Together with custom
annotations it becomes possible to fix some API design issues (e.g. mismatch between documentation and webservice
response), to create a clean SDK.

##### @MagicSunday\JsonMapper\Annotation\ReplaceNullWithDefaultValue
This annotation is used to inform the JsonMapper that an existing default value should be used when
setting a property, if the value derived from the JSON is a NULL value instead of the expected property type.

This can be necessary, for example, in the case of a bad API design, if the API documentation defines a
certain type (e.g. array), but the API call itself then returns NULL if no data is available for a property
instead of an empty array that can be expected.

    /**
     * @var array<string>
     *
     * @MagicSunday\JsonMapper\Annotation\ReplaceNullWithDefaultValue
     */
    public array $array = [];

If the mapping tries to assign NULL to the property, the default value will be used, as annotated.


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
$nameConverter = new \MagicSunday\CamelCasePropertyNameConverter();
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

Add a custom type handler to map a JSON value into a custom class:
```php
// Perform special treatment if an object of type Bar should be mapped 
$mapper->addType(
    Bar::class,
    /** @var mixed $value JSON data */
    static function ($value): ?Bar {
        return $value ? new Bar($value['name']) : null;
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


## Development

### Testing
```bash
composer update
vendor/bin/phpcs ./src  --standard=PSR12
vendor/bin/phpstan analyse -c phpstan.neon
vendor/bin/phpunit
```
