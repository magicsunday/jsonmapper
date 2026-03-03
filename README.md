<h1 align="center">JsonMapper: JSON to PHP Object Mapping</h1>

<p align="center">
  Map JSON data to strongly-typed PHP classes using Symfony's PropertyInfo and PropertyAccess components.
</p>

<!-- Row 1: CI / Quality badges -->
<p align="center">
  <a href="https://github.com/magicsunday/jsonmapper/actions/workflows/ci.yml"><img src="https://github.com/magicsunday/jsonmapper/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
</p>

<!-- Row 2: Standards / Tooling badges -->
<p align="center">
  <a href="https://phpstan.org/"><img src="https://img.shields.io/badge/PHPStan-max%20level-brightgreen.svg" alt="PHPStan Max Level"></a>
  <a href="https://phpunit.de/"><img src="https://img.shields.io/badge/PHPUnit-12-blue.svg" alt="PHPUnit 12"></a>
  <a href="https://getrector.com/"><img src="https://img.shields.io/badge/Rector-2.0-orange.svg" alt="Rector 2.0"></a>
  <a href="https://www.php-fig.org/psr/psr-12/"><img src="https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg" alt="PSR-12"></a>
</p>

<!-- Row 3: Compatibility badges -->
<p align="center">
  <a href="composer.json"><img src="https://img.shields.io/badge/php-8.3|8.4|8.5-blue" alt="PHP Version"></a>
</p>

<!-- Row 4: Project badges -->
<p align="center">
  <a href="https://github.com/magicsunday/jsonmapper/releases/latest"><img src="https://img.shields.io/github/v/release/magicsunday/jsonmapper?sort=semver" alt="Latest version"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/magicsunday/jsonmapper" alt="License"></a>
</p>

---

## 📌 Overview
JsonMapper is a PHP library that maps JSON data to strongly-typed PHP classes (DTOs, value objects, entities) using reflection and PHPDoc annotations. It leverages Symfony's PropertyInfo and PropertyAccess components to provide flexible, extensible JSON-to-PHP object mapping.

| Key      | Value                                              |
|----------|----------------------------------------------------|
| Package  | `magicsunday/jsonmapper`                           |
| PHP      | `^8.3`                                             |
| Main API | `MagicSunday\JsonMapper`                           |
| Output   | Mapped PHP objects + optional `MappingReport`      |

## ❓ What is this?
JsonMapper takes decoded JSON (via `json_decode`) and hydrates typed PHP objects, including nested objects, collections, enums, DateTime values, and custom types. It supports both lenient and strict mapping modes with detailed error reporting.

## 🎯 Why does this exist?
Mapping API responses or configuration payloads to typed PHP classes is a common task that involves repetitive boilerplate. JsonMapper automates this with a clean, extensible architecture based on Symfony components, supporting advanced scenarios like polymorphic APIs, custom name conversion, and recursive collection handling.

## 🚀 Usage

```bash
composer require magicsunday/jsonmapper
```

### Quick start

```php
namespace App\Dto;

use ArrayObject;

final class Comment
{
    public string $message;
}

/**
 * @extends ArrayObject<int, Comment>
 */
final class CommentCollection extends ArrayObject
{
}

/**
 * @extends ArrayObject<int, Article>
 */
final class ArticleCollection extends ArrayObject
{
}

final class Article
{
    public string $title;

    /**
     * @var CommentCollection<int, Comment>
     */
    public CommentCollection $comments;
}
```

```php
require __DIR__ . '/vendor/autoload.php';

use App\Dto\Article;
use App\Dto\ArticleCollection;
use MagicSunday\JsonMapper;

$single = json_decode('{"title":"Hello world","comments":[{"message":"First!"}]}', associative: false, flags: JSON_THROW_ON_ERROR);
$list = json_decode('[{"title":"Hello world","comments":[{"message":"First!"}]},{"title":"Second","comments":[]}]', associative: false, flags: JSON_THROW_ON_ERROR);

$mapper = JsonMapper::createWithDefaults();

$article = $mapper->map($single, Article::class);
$articles = $mapper->map($list, Article::class, ArticleCollection::class);
```

`JsonMapper::createWithDefaults()` wires the default Symfony `PropertyInfoExtractor` (reflection + PhpDoc) and a `PropertyAccessor`. For custom extractors, caching, or a specialised accessor see [Manual instantiation](docs/recipes/manual-instantiation.md).

### PHP classes

Annotate all properties with the requested type. For collections, use the phpDocumentor collection annotation type:

```php
/** @var SomeCollection<DateTime> $dates */
/** @var SomeCollection<string> $labels */
/** @var Collection\\SomeCollection<App\\Entity\\SomeEntity> $entities */
```

## 📚 Documentation

* [API reference](docs/API.md)
* Recipes
  * [Manual instantiation](docs/recipes/manual-instantiation.md) — custom extractors, name converters, class maps, collection mapping
  * [Type converters and custom class maps](docs/recipes/type-converters.md) — custom type handlers, runtime class resolution
  * [Error handling strategies](docs/recipes/error-handling.md) — strict vs. lenient mode, error collection
  * [Performance hints](docs/recipes/performance.md) — PSR-6 type caching
  * [Using mapper attributes](docs/recipes/using-attributes.md) — ReplaceProperty, ReplaceNullWithDefaultValue
  * [Mapping JSON to PHP enums](docs/recipes/mapping-with-enums.md)
  * [Mapping nested collections](docs/recipes/nested-collections.md)
  * [Using a custom name converter](docs/recipes/custom-name-converter.md)

## 🛠️ Development

Prerequisites:

- PHP `^8.3`
- Extensions: `json`

Install dependencies:

```bash
composer install
```

Run the mandatory quality gate:

```bash
composer ci:test
```

`ci:test` includes:

- Linting (`phplint`)
- Unit tests (`phpunit`)
- Static analysis (`phpstan`)
- Refactoring dry-run (`rector --dry-run`)
- Coding standards dry-run (`php-cs-fixer --dry-run`)
- Copy/paste detection (`jscpd`)

## 🤝 Contributing

See `CONTRIBUTING.md` for contributor workflow and minimal setup.

If contributions are prepared or modified by an LLM/agent, follow `AGENTS.md` (and `tests/AGENTS.md` for test-only scope).
