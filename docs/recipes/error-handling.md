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

## Security: do not forward a mapping message to a client

A mapping message is written for a developer reading a log. It embeds two things that do not belong
in a response body:

- **Your internal class names.** `Unknown property $.foo on App\Domain\Billing\InvoiceLine.` tells
  a client how your DTOs are laid out and named.
- **A string the payload chose.** The path is built from the payload's own keys, so a message
  reflects an attacker-supplied value back verbatim. Rendered unescaped, that is a cross-site
  scripting vector in the consumer's UI, not in the mapper.

The payload's *values* are never included — only `get_debug_type()` of them — so a mapping message
cannot leak the data itself. That is the one thing it is safe about.

Build client-facing text from the structured accessors instead, and escape what you emit:

```php
use Symfony\Component\TypeInfo\TypeIdentifier;

$isBuiltinType = static function (string $type): bool {
    foreach (explode('|', $type) as $part) {
        if (!TypeIdentifier::tryFrom($part) instanceof TypeIdentifier) {
            return false;
        }
    }

    return true;
};

foreach ($result->getReport()->getErrors() as $error) {
    $exception = $error->getException();

    $clientMessage = match (true) {
        $exception instanceof UnknownPropertyException  => 'Unsupported field: ' . $exception->getPropertyName(),
        $exception instanceof MissingPropertyException  => 'Required field missing: ' . $exception->getPropertyName(),
        // getExpectedType() is a builtin name for a scalar target but a fully qualified CLASS NAME
        // for an object, enum or date target — so echoing it verbatim leaks exactly what this
        // section warns about. Emit it only when every part of it is a builtin.
        //
        // Split on '|': a nullable or union target yields 'int|null' or 'int|string', which no
        // single-token check can ever match — and a nullable scalar mismatch is among the most
        // common failures there is, so a naive check silently degrades most messages to
        // 'Invalid value'. TypeIdentifier is the authority on what a builtin name is, rather than
        // a hand-kept literal that drifts.
        $exception instanceof TypeMismatchException     => $isBuiltinType($exception->getExpectedType())
            ? 'Expected type: ' . $exception->getExpectedType()
            : 'Invalid value',
        default                                         => 'Invalid value',
    };

    // getPath() is built from the payload's own keys, so it is attacker-controlled and unbounded.
    // Echoing the submitted field name is what a field-error API has to do — but it is untrusted
    // input on the way out: cap it, and escape it for whatever sink it reaches. JSON encoding is
    // not enough if the consumer's UI later injects it as HTML.
    $response[] = [
        'field'   => mb_substr($error->getPath(), 0, 256),
        'message' => $clientMessage,
    ];
}
```

Every mapping exception exposes what it knows through accessors, so nothing here needs the message
string parsed:

| Exception | Accessors beyond `getPath()` |
|-----------|------------------------------|
| `UnknownPropertyException` | `getPropertyName()`, `getClassName()` |
| `MissingPropertyException` | `getPropertyName()`, `getClassName()` |
| `MissingConstructorArgumentException` | `getArgumentName()`, `getClassName()` |
| `ReadonlyPropertyException` | `getPropertyName()`, `getClassName()` |
| `TypeMismatchException` | `getExpectedType()`, `getActualType()` — see the caveat below |
| `CollectionMappingException` | `getActualType()` |

`getClassName()` is there for logs and for deciding what to say — not for saying it. The same goes
for `getExpectedType()` whenever the target is an object, an enum or a date type: it returns the
fully qualified class name then, and only for builtin targets is it a safe token like `int`.

The rule covers **every** exception the mapper raises, not only the `MappingException` hierarchy. A
configuration defect — an unresolvable collection element type, a class-map resolver returning
something unusable — escapes as a `DomainException` or `InvalidArgumentException`, past the report
entirely, into whatever generic handler you wrote. Those messages name internal classes too.

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
class name that does not exist, a class that cannot be instantiated, or a collection whose element
type cannot be resolved, is a defect in the call rather than in the payload.

"Cannot be instantiated" covers more than a typo: an **abstract class**, an **enum** and a class
with a **private constructor** all pass `class_exists()`, and an **interface** resolves too (the
resolver accepts one through `interface_exists()`) — yet all four make `new $className` raise. They
are refused where they would be built with an `InvalidArgumentException` naming the class, rather
than reaching the constructor and raising a native `Error` that no error collection can see. To map onto an interface or an abstract base,
register the concrete target with `addCustomClassMapEntry()` — the check runs where an object is
actually built, on the concrete class the map produced, so a base used only as the declared element
type of a polymorphic list is never refused.

The refused name is echoed only when it is the name **you** passed. A class-map resolver's input is
the payload, so when the resolver picked the target the message names the requested class instead —
enough to find the entry, without reflecting a payload-chosen string into a response body.

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
