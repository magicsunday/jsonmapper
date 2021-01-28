[![License: GPL v3](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/magicsunday/jsonmapper/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/magicsunday/jsonmapper/?branch=master)
[![Code Climate](https://codeclimate.com/github/magicsunday/jsonmapper/badges/gpa.svg)](https://codeclimate.com/github/magicsunday/jsonmapper)

# JsonMapper
This module provides a mapper to map JSON to PHP classes utilizing Symfony's property info and access packages.

## Installation

### Using Composer
To install using [composer](https://getcomposer.org/), just run the following command from the command line.

```
composer require magicsunday/jsonmapper
```

To remove the module run:
```
composer remove magicsunday/jsonmapper
```


## Usage
Create instances of Symfony's property info extractors to use together with the mapper. Each list of extractors
could contain any number of available extractors. You could also create your own extractors to adjust the process
of extracting property info to your needs.

```php
$listExtractors = [ new ReflectionExtractor() ];
$typeExtractors = [ new ReflectionExtractor(), new PhpDocExtractor() ];
$propertyInfoExtractor = new PropertyInfoExtractor($listExtractors, $typeExtractors);
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
$mapper = new JsonMapper(
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
    static function ($value): ?Bar {
        return $value ? new Bar($value) : null;
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