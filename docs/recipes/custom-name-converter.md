# Using a custom name converter

Property name converters translate JSON keys to PHP property names. JsonMapper provides `CamelCasePropertyNameConverter` out of the box and allows you to supply your own implementation of `PropertyNameConverterInterface`.

`CamelCasePropertyNameConverter` reads `_`, `-` and a space as word boundaries, so `camel_case_property`, `camel-case-property` and `camel case property` all name `camelCaseProperty`. A digit stays part of its segment: `address_line_1` becomes `addressLine1`, not `addressLine` plus a number.

An **all-ASCII** key written entirely in upper case — `ADDRESS_LINE_1`, `ID` — states its boundaries with separators and nothing with its case, so the case is discarded first and the result is `addressLine1` and `id`. A key that contains any lower-case letter keeps its case, because there the case *is* a boundary: `PascalCase` becomes `pascalCase`, and `HTTPServer` is left as `hTTPServer` rather than flattened to `httpserver` — where one word ends inside an acronym is not something the key states. A key with a **non-ASCII** letter is left as it arrived — `strtolower()` folds ASCII only, and a correct fold would need `ext-mbstring`, which the library does not require. Write your own converter when your API relies on either.

Conversion is idempotent for any name that can be a PHP property identifier: running an already-converted name through it again returns the same name, so a `ReplaceProperty` alias may spell the target either way.

> **Several spellings can name one property.** Because case and separators are folded, `id`, `ID`,
> `Id` and (with the default converter) `user_id`/`userId` all resolve to the same property. Two of
> them in one payload is a last-one-wins overwrite in payload order, and a key you rejected or
> inspected *before* mapping — an exact-key denylist, `unset($payload['role'])`, a validator reading
> `$payload['amount']` — does **not** see the other spellings. Run any security-relevant key check on
> the **converted** name, not on the raw payload key.

```php
<?php
declare(strict_types=1);

namespace App\Converter;

use MagicSunday\JsonMapper\Converter\PropertyNameConverterInterface;

final class UpperSnakeCaseConverter implements PropertyNameConverterInterface
{
    public function convert(string $name): string
    {
        // Normalise keys by removing underscores and lowercasing them.
        return strtolower(str_replace('_', '', $name));
    }
}
```

```php
<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Converter\UpperSnakeCaseConverter;
use MagicSunday\JsonMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

// Collect metadata and provide the custom converter.
$propertyInfo = new PropertyInfoExtractor(
    listExtractors: [new ReflectionExtractor()],
    typeExtractors: [new PhpDocExtractor()],
);
$propertyAccessor = PropertyAccess::createPropertyAccessor();
$converter = new UpperSnakeCaseConverter();

$mapper = new JsonMapper($propertyInfo, $propertyAccessor, $converter);
```

Name converters are stateless and should be declared `final`. They are applied to every property access during mapping, so keep the implementation idempotent and efficient.

Test coverage: `tests/JsonMapper/DocsCustomNameConverterTest.php`.
