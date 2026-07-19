# Error handling strategies

The mapper operates in a lenient mode by default. Switch to strict mapping when every property must be validated.

## Strict mode with error collection

```php
require __DIR__ . '/vendor/autoload.php';

use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

final class Article
{
    public string $title;
}

// Decode the JSON payload that should comply with the DTO schema.
$payload = json_decode('{"title":"Strict example"}', associative: false, flags: JSON_THROW_ON_ERROR);

// Prepare the mapper with Symfony metadata extractors.
$propertyInfo = new PropertyInfoExtractor(
    listExtractors: [new ReflectionExtractor()],
    typeExtractors: [new PhpDocExtractor()],
);
$propertyAccessor = PropertyAccess::createPropertyAccessor();

$mapper = new JsonMapper($propertyInfo, $propertyAccessor);

// Enable strict validation and collect every encountered error.
$config = JsonMapperConfiguration::strict()->withErrorCollection(true);

// Map while receiving a result object that contains the mapped DTO and issues.
$result = $mapper->mapWithReport($payload, Article::class, configuration: $config);

var_dump($result->getValue());
```

## What the report covers

In lenient mode `mapWithReport()` records every **mapping** failure - the `MappingException`
hierarchy - regardless of where in the payload it occurs. A failure on the **root** object is
reported exactly like one on a nested property; the mapped value is then `null`, so a caller can
tell "nothing was produced" apart from a partially populated object:

```php
final readonly class ImmutableArticle
{
    public function __construct(public string $title)
    {
    }
}

// The payload supplies no title, so the object cannot be constructed at all.
$result = $mapper->mapWithReport([], ImmutableArticle::class);

$result->getValue();    // null - the object could not be built

foreach ($result->getReport()->getErrors() as $error) {
    $error->getPath();         // '$'
    $error->getMessage();      // human readable description
    $error->getException();    // MissingConstructorArgumentException
}
```

`getErrors()` returns `MappingError` records, not exceptions: each carries the path, the message and
the originating exception.

A rejected value for an **object** target never reaches its property. The property keeps whatever it
had - its default, or nothing at all if it was never initialised - and the failure is in the report
instead. For a collection, only the offending element is dropped; its valid siblings survive, and
list keys stay gap-free.

A **builtin** target behaves the same way when the value has no meaningful cast: a composite
reaching a scalar property is rejected rather than assigned, so a `string` property can no longer
end up holding `'Array'`. A scalar that merely arrives as the wrong scalar type is still coerced -
see the coercion contract below for which conversions happen silently and which are reported.

A scalar payload against an object target is rejected only when the target actually needs
constructor arguments. A class whose constructor can be called without any still yields an instance,
with no error recorded - the scalar simply supplies nothing.

A property that declares no type at all is not coerced: it makes no claim about its value, so the
decoded payload is assigned unchanged - array, object, scalar or null alike, with nothing reported.

Configuration problems are not mapping failures and still surface as exceptions in both modes: a
class name that does not exist, or a collection whose element type cannot be resolved, is a defect
in the call rather than in the payload.

## Which entry point throws

Strict mode decides *what* counts as a failure. It does **not** decide what happens to one - that is
the entry point's job:

| Call | Strict mode | Lenient mode |
|------|-------------|--------------|
| `map()` | throws on the first failure | collects, returns a partial result |
| `mapWithReport()` | collects everything into the report | collects everything into the report |

`mapWithReport()` never throws a `MappingException`, whatever configuration it is given. Returning a
report is the whole reason the method exists, so a configuration that made it throw would leave the
caller with no way to get one. What `strict()` still changes is the *content* of that report: it adds
failures lenient mode would never raise, such as a missing or unknown property.

```php
$strict = JsonMapperConfiguration::strict();

$result = $mapper->mapWithReport([], Article::class, configuration: $strict);

$result->getValue();                      // null - nothing could be built
$result->getReport()->getErrorCount();    // every strict violation, not just the first
```

Use `map()` when the first failure should abort. Use `mapWithReport()` when you want the full picture.

## Lenient mode

For tolerant APIs combine `JsonMapperConfiguration::lenient()` with `->withIgnoreUnknownProperties(true)` or `->withTreatNullAsEmptyCollection(true)` to absorb schema drifts.

### What lenient mode coerces, and what it refuses

Lenient mode absorbs a *scalar* that arrives with the wrong type, and it does so in two distinct
ways. Where the payload is a recognisable **representation** of the target type, it is normalised
silently — nothing is reported, because nothing was lost:

| Payload | Target | Result |
|---------|--------|--------|
| `'42'` | `int` | `42` |
| `'2.5'` | `float` | `2.5` |
| `3` | `float` | `3.0` |
| `2.0` | `int` | `2` |
| `'true'`, `'1'`, `1` | `bool` | `true` |
| `'false'`, `'0'`, `0` | `bool` | `false` |

A `float` reaching an `int` property is only accepted silently when it *is* that integer — `2.0`
maps to `2`. A fractional value is a genuine mismatch: it is reported, and coerced only in lenient
mode. Strict mode raises.

Everything else is a genuine mismatch: the value is cast **and** recorded in the report, so the
mapping succeeds while the drift stays visible.

| Payload | Target | Result |
|---------|--------|--------|
| `42` | `string` | `'42'` |
| `true` | `string` | `'1'` |
| `'abc'` | `int` | `0` |
| `'yes'`, `5` | `bool` | `true` |

An `array` or an object reaching a **scalar** property is refused instead of cast. PHP itself would
not object in every case — casting a non-empty array to `string` yields the literal `'Array'` plus a
warning, while casting it to `bool`, `int` or `float` silently yields `true`, `1` or `1.0`. None of
those carry information from the payload, so the mapper records a `TypeMismatchException` rather
than handing back a plausible-looking value derived from nothing.

This applies to objects with a `__toString()` method as well: the decision is made on the target
type, not on what the value happens to be capable of.

Composites reaching an `array` or `object` property are *not* refused — those casts are meaningful
and still happen. They are reported like any other cast, so "not refused" does not mean "silent".

A refused value is recorded exactly once. A declared property keeps its default; one declared
without a default is left uninitialised, so read it back only after checking the report. (A value
required by the constructor is a different case — that raises `MissingConstructorArgumentException`
instead.)

```php
$result = $mapper->mapWithReport($payload, Article::class);

if ($result->getReport()->hasErrors()) {
    foreach ($result->getReport()->getErrors() as $error) {
        // $error->getPath(), $error->getMessage()
    }
}
```

Test coverage: `tests/JsonMapper/DocsErrorHandlingTest.php`, `tests/JsonMapper/RootLevelErrorHandlingTest.php`, `tests/JsonMapper/ScalarPayloadOnObjectTest.php` and `tests/JsonMapper/JsonMapperErrorHandlingTest.php`.
