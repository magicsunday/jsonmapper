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
hierarchy - regardless of where in the payload it occurs, up to the ceiling described under
[Bounding the report](#bounding-the-report). A failure on the **root** object is
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

## Bounding the report

The number of failures a payload can cause grows with its size: a body of many unknown keys files
one record per key, each carrying a path built from those keys. A report therefore keeps at
most `MappingContext::DEFAULT_MAX_ERRORS` records unless you set another ceiling:

```php
$config = JsonMapperConfiguration::lenient()->withMaxErrors(50);

$report = $mapper->mapWithReport($payload, Article::class, configuration: $config)->getReport();

$report->getErrorCount();        // at most 50
$report->getDroppedErrorCount(); // how many further failures were not kept
$report->isTruncated();          // true when the report is incomplete
```

A report that dropped failures says so, so an empty tail is never mistaken for a clean one. The dropped
count says how many failures the report lost, not how many the mapper met: the failures of a union member
that was tried and rejected are not part of the report to begin with.

The ceiling bounds the finished report. It does not bound the time spent mapping a hostile payload,
and the memory a kept record holds grows with the nesting depth of the payload, because a record
keeps the exception it was made from together with its stack trace. Over a chain of nested objects the
total grows faster than the depth does, so a deep chain stays expensive with few records kept. What
bounds it is the depth limit: a payload cannot nest deeper than `MappingContext::DEFAULT_MAX_DEPTH`
levels unless you set another limit.

```php
$config = JsonMapperConfiguration::lenient()->withMaxDepth(16);
```

The limit bounds the depth of one chain, not the width of the payload: a wide payload whose elements
fail keeps as many records as the ceiling allows, each holding a trace as deep as its level, so the
worst case is the ceiling times the limit. A level is one step of a path, a property or a collection
element, so the scalar at the end of a chain counts as well. A level beyond the limit is not mapped. It is refused with a `DepthLimitExceededException`,
which a lenient run files in the report and a strict `map()` raises, and `getLimit()` names the limit.
Inside a nullable or union-typed object property the failure is not relabelled a type mismatch of that
property, unlike other failures recorded there. The limit does not bound the work of a union whose members
nest in each other: a payload that no member accepts is tried against every member at every level, which
multiplies the work per level long before the limit is reached. For untrusted input lower the limit and the
ceiling, and limit the size of the request body before it is decoded. While a
member of a union is being tried its records are held up to the ceiling above where the attempt began,
plus one record for each union nested inside it, so the mapper can briefly hold more than the ceiling
while a union is tried. They are dropped when the attempt is rejected. When it is accepted, what it
recorded is cut back to the ceiling and the rest is counted as dropped, like any other failure.

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
| `AmbiguousPropertyException` | `getPropertyName()`, `getClassName()`, `getPayloadKeys()` |
| `MissingPropertyException` | `getPropertyName()`, `getClassName()` |
| `MissingConstructorArgumentException` | `getArgumentName()`, `getClassName()` |
| `ReadonlyPropertyException` | `getPropertyName()`, `getClassName()` |
| `TypeMismatchException` | `getExpectedType()`, `getActualType()` — see the caveat below |
| `CollectionMappingException` | `getActualType()` |
| `DepthLimitExceededException` | `getLimit()` |

`getClassName()` is there for logs and for deciding what to say — not for saying it. The same goes
for `getExpectedType()` whenever the target is an object, an enum or a date type: it returns the
fully qualified class name then, and only for builtin targets is it a safe token like `int`. When a
scalar is given for an object property or at the entry point, or a null payload for a collection
class, it is the class that was asked for, the property's declared class or the one the caller
passed, and never a class that a class-map resolver produced, because a resolver may derive its
answer from the payload. For the same reason, when a property write or a constructor argument
refuses an object, the mismatch reports the actual type as `object` and never as the class it was
built from. A setter with a variadic parameter is the exception, because it is called directly and
a refused element escapes as a native error that names the class. Every other mismatch reports the
debug type of the payload value, so an object that the payload itself carries is named by its class.
A failure raised while a resolved object is built or filled is about that object and names it, for
example a missing constructor argument or an unknown key. A value refused by one of its properties
is typed by that property. Behind a nullable property an unknown key or a refused value surfaces as
a mismatch of the declared class instead, while a missing constructor argument still names the
resolved class. Elements of a list mapped with an element class are typed with the class the
resolver produced for the whole list. So do not forward an exception that was raised below a
class-mapped property into a response, and keep the others out of it too.

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

### Which declaration wins

A property's type is read from its docblock first, then from the native declaration. That order is
right when the docblock **refines** the native type and wrong when it **widens** it:

| Native type | Docblock | Authoritative | Why |
|---|---|---|---|
| `Simple` | — | native | the only declaration |
| — | `@var Simple` | docblock | the only declaration |
| `array` | `@var string[]` | **docblock** | a refinement — this is what reading docblocks is for |
| `int` | `@var int\|null` | **native** | a widening — the docblock cannot grant what the target rejects |

The last row is enforced where the value is handed over, not where the type is resolved, because the
two lanes hand it to different declarations:

* Assigned to a **property**, the value goes through the accessor, and a refusal comes back as a
  reported type mismatch naming the type that refused it. That includes a property reachable only
  through a setter, where the setter may legitimately accept more than the field it writes.
* Passed to a **constructor parameter**, there is no assignment to intercept: the value would reach
  `new $className()` and raise a native error the report cannot see. So every argument drawn from
  the payload is checked against the parameter's own declaration first, and a value the parameter
  would refuse is reported instead. What fills the gap then is exactly what fills it when the value
  was simply absent: a parameter with a default takes its default and the rest of the object maps; a
  required parameter records a second `MissingConstructorArgumentException` and the object is not
  built — the same two-error, no-object outcome a required parameter already produces when its value
  fails ordinary conversion.

A refused element of a **variadic** parameter is dropped on its own, keeping its valid siblings, the
way an unmappable collection element is — one bad entry does not discard the rest of the tail.

The constructor check keys on the declaration, not on a contradicting docblock, so it also covers
the case where nothing contradicts anything: a property that declares no type at all makes no claim
the mapper can check, and its value meets the parameter's declaration for the first time at the
call.

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

## When the write itself refuses the value

Conversion runs against the type the mapper could derive from a property, and that is not always the
type the property declares. A property typed by an **intersection** (`A&B`) is the reachable case:
the mapper cannot model it, so it accepts any converted value and the property refuses it at the
write. That refusal is reported as a `TypeMismatchException` at the property path, naming the
declared type — not left to escape as a native error. A no-default intersection property is also
reported as **missing** in strict mode, because no absent value satisfies it.

The same holds when the type metadata of a property, such as one exposed through an accessor pair,
names something that is not a class name at all, the source text of an intersection for example. The
value cannot fit a type the mapper does not model, so it is reported as a `TypeMismatchException` at
the property path. That holds for the class of an object type and for the wrapper class of a
collection type alike. A well-formed class name that resolves to nothing is a different case, a
mistake of your declarations, and still escapes as a `DomainException`. So does a malformed class
name you pass to `map()` or `mapWithReport()` yourself, since no payload can choose it.

A `TypeError` raised **inside your own setter body** (the setter passes the value on to another
strict-typed call) is deliberately *not* caught and re-labelled. It is a bug in the setter rather
than a payload mismatch, so it propagates as itself instead of being reported against the payload
value and buried in the report.

## Which entry point throws

Strict mode decides *what* counts as a failure. It does **not** decide what happens to one - that is
the entry point's job:

| Call | Strict mode | Lenient mode |
|------|-------------|--------------|
| `map()` | throws on the first failure | collects, returns a partial result |
| `mapWithReport()` | collects into the report, up to the ceiling | collects into the report, up to the ceiling |

`mapWithReport()` never throws a `MappingException`, whatever configuration it is given. Returning a
report is the whole reason the method exists, so a configuration that made it throw would leave the
caller with no way to get one. What `strict()` still changes is the *content* of that report: it adds
failures lenient mode would never raise, such as a missing or unknown property. It reports a missing
property at any depth, including for an object reached through a nullable or union-typed property.
Where a union offers several object types, a missing property does not decide which one is used.
The first type whose types match the payload is used, and what it lacks is reported. That holds for a type
whose properties are set after construction. A type with a required constructor argument is rejected
for lacking it, like any other type that cannot be built from the payload.

An omitted value is one record, whether the class takes it through a constructor or through a property.
In strict mode the omission of a required value is reported as a missing property, and the failed
construction that follows from it is not recorded a second time, because the value is `null` and the
object was not built. For a nullable or union-typed property the member that cannot be built is
rejected and its failed construction is the single record. Outside strict mode no missing property is
reported, so the failed construction is the only record.

Two cases stay two records, because they are two facts. A constructor parameter that is named
differently from the property it feeds is reported as a missing property, and the failed construction
names the parameter, so the two records are about two names. A value that was supplied and refused is
recorded where it was converted, and the object that then lacks its argument is recorded as one that
could not be built, which the first record does not tell the caller.

```php
$strict = JsonMapperConfiguration::strict();

$result = $mapper->mapWithReport([], Article::class, configuration: $strict);

$result->getValue();                      // null - nothing could be built
$result->getReport()->getErrorCount();    // every strict violation up to the ceiling, not just the first
```

Use `map()` when the first failure should abort. Use `mapWithReport()` when you want the full picture.

A caller that passes its own `MappingContext` to an aborting `map()` can read the records after catching
the exception. The failure is recorded once, at the path of the value that was rejected, however deeply
that value sits. The enclosing objects let the exception pass without recording it again.

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
