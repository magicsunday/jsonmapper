[![License: GPL v3](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)

[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat)](https://github.com/phpstan/phpstan)
[![PHPStan](https://img.shields.io/badge/PHP_CodeSniffer-PSR12-brightgreen.svg?style=flat)](https://github.com/squizlabs/PHP_CodeSniffer)
[![PHPStan](https://img.shields.io/badge/PHPUnit-passed-brightgreen.svg?style=flat)](https://github.com/sebastianbergmann/phpunit)

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

### Instantiation
Create instances of Symfony's property info extractors to use together with the mapper. Each list of extractors
could contain any number of available extractors. You could also create your own extractors to adjust the process
of extracting property info to your needs.

To use the `PhpDocExtractor` extractor you need to install the `phpdocumentor/reflection-docblock` library too.

```php
use \Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use \Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use \Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use \Symfony\Component\PropertyAccess\PropertyAccessor;
```

```php
// A common extractor setup
$listExtractors = [ new ReflectionExtractor() ];
$typeExtractors = [ new PhpDocExtractor() ];
$propertyInfoExtractor = new PropertyInfoExtractor($listExtractors, $typeExtractors);
```

To extract PHP 7.4 typed properties you should use the `ReflectionExtractor` inside the type extractor list too:
```php
$typeExtractors = [ new ReflectionExtractor(), new PhpDocExtractor() ];
```

Create an instance of the property accessor:
```php
$propertyAccessor = PropertyAccess::createPropertyAccessor();
```

Add an optional class map to the JsonMapper in order to change the default mapping behaviour.
```php
$classMap = [];
```

Create an instance of the JsonMapper:
```php
$mapper = new \MagicSunday\JsonMapper(
    $propertyInfoExtractor,
    $propertyAccessor,
    $classMap
);
```

Add a custom type handler to map a JSON value into a custom class:
```php
// Perform special treatment if an object of type Bar should be mapped 
$mapper->addType(
    Bar::class,
    /* @var mixed $value JSON data */
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
