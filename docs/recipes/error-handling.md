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

For a **builtin** target the value is currently still coerced and assigned after the mismatch has
been recorded, so a `string` property can end up holding `'Array'`. Check the report before trusting
such a value. Aligning this with the object behaviour is tracked in issue 63.

A scalar payload against an object target is rejected only when the target actually needs
constructor arguments. A class whose constructor can be called without any still yields an instance,
with no error recorded - the scalar simply supplies nothing.

Configuration problems are not mapping failures and still surface as exceptions in both modes: a
class name that does not exist, or a collection whose element type cannot be resolved, is a defect
in the call rather than in the payload.

In strict mode the same mapping failures are thrown on the first occurrence rather than collected.

## Lenient mode

For tolerant APIs combine `JsonMapperConfiguration::lenient()` with `->withIgnoreUnknownProperties(true)` or `->withTreatNullAsEmptyCollection(true)` to absorb schema drifts.

### What lenient mode coerces, and what it refuses

Lenient mode absorbs a *scalar* that arrives with the wrong type. The value is converted and the
conversion is recorded in the report, so the mapping succeeds and the drift stays visible:

| Payload | Target | Result |
|---------|--------|--------|
| `42` | `string` | `'42'` |
| `'42'` | `int` | `42` |
| `3.9` | `int` | `3` (truncated) |
| `'true'`, `'1'`, `'yes'` | `bool` | `true` |
| `'false'`, `'0'`, `0` | `bool` | `false` |
| `'abc'` | `int` | `0` |

An `array` or an object reaching a **scalar** property is refused instead. PHP would not object —
casting an array to `string` yields the literal `'Array'` plus a warning, and casting it to `bool`,
`int` or `float` silently yields `true`, `1` or `1.0`. None of those carry information from the
payload, so the mapper records a `TypeMismatchException` and leaves the property at its default
rather than handing back a plausible-looking value derived from nothing.

This applies to objects with a `__toString()` method as well: the decision is made on the target
type, not on what the value happens to be capable of.

Composites reaching an `array` or `object` property are *not* affected — those casts are
meaningful and still happen.

A refused value is recorded exactly once. Where the property has no default, it is left
uninitialised, so read it back only after checking the report:

```php
$result = $mapper->mapWithReport($payload, Article::class);

if ($result->getReport()->hasErrors()) {
    foreach ($result->getReport()->getErrors() as $error) {
        // $error->getPath(), $error->getMessage()
    }
}
```

Test coverage: `tests/JsonMapper/DocsErrorHandlingTest.php`, `tests/JsonMapper/RootLevelErrorHandlingTest.php`, `tests/JsonMapper/ScalarPayloadOnObjectTest.php` and `tests/JsonMapper/JsonMapperErrorHandlingTest.php`.
