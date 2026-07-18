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

var_dump($result->getMappedValue());
```

## What the report covers

In lenient mode `mapWithReport()` records every mapping failure and never throws - regardless of
where the failure occurs. A failure on the **root** object is reported exactly like one on a nested
property; the mapped value is then `null`, so a caller can tell "nothing was produced" apart from a
partially populated object:

```php
$result = $mapper->mapWithReport([], ImmutableDto::class);

$result->getValue();                  // null - the object could not be built
$result->getReport()->getErrors();    // MissingConstructorArgumentException
```

A rejected value never reaches its target. The property keeps whatever it had - its default, or
nothing at all if it was never initialised - and the failure is in the report instead. For a
collection, only the offending element is dropped; its valid siblings survive, and list keys stay
gap-free.

In strict mode the same failures are thrown on the first occurrence rather than collected.

## Lenient mode

For tolerant APIs combine `JsonMapperConfiguration::lenient()` with `->withIgnoreUnknownProperties(true)` or `->withTreatNullAsEmptyCollection(true)` to absorb schema drifts.
