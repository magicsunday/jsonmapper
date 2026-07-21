# Using mapper attributes

JsonMapper ships with attributes that can refine how JSON data is mapped to PHP objects.

## `ReplaceNullWithDefaultValue`
Use this attribute on properties that should fall back to their default value when the JSON payload explicitly contains `null`.

```php
<?php
declare(strict_types=1);

namespace App\Dto;

use MagicSunday\JsonMapper\Attribute\ReplaceNullWithDefaultValue;

final class User
{
    /**
     * @var list<string>
     */
    #[ReplaceNullWithDefaultValue]
    public array $roles = [];
}
```

When a payload contains `{ "roles": null }`, the mapper keeps the default empty array instead of overwriting it with `null`.

Test coverage: `tests/JsonMapperTest.php::mapNullToDefaultValueUsingAttribute`.

## `ReplaceProperty`
Apply this attribute at class level to redirect one or more incoming property names to a different target property.

```php
<?php
declare(strict_types=1);

namespace App\Dto;

use MagicSunday\JsonMapper\Attribute\ReplaceProperty;

#[ReplaceProperty('fullName', replaces: 'first_name')]
#[ReplaceProperty('fullName', replaces: 'name')]
final class Contact
{
    public string $fullName;
}
```

Both `first_name` and `name` keys populate the `$fullName` property. Declare one attribute per alias.

The attributes are collected into a map keyed by the alias, so their declaration order carries no
meaning: distinct aliases never compete. Two attributes declaring the *same* alias are a
redeclaration - the last one wins. If a single payload carries several aliases for one property,
the last key the payload happens to supply is the one that ends up in the property, so a payload
should not send more than one alias for the same target.

Test coverage: `tests/Attribute/ReplacePropertyTest.php::replaceProperty`.

## `UnknownPropertyCollector`
Mark one property as the sink for every source key that matches no declared property. Instead of being ignored (lenient mode) or reported (strict mode), the unknown keys are collected into an associative `array<string, mixed>` — the original payload key mapped to its raw, unconverted value — and assigned to the marked property as-is. This preserves unmodelled input rather than losing it.

```php
<?php
declare(strict_types=1);

namespace App\Dto;

use MagicSunday\JsonMapper\Attribute\UnknownPropertyCollector;

final class Record
{
    /**
     * @param array $additional A raw map of unknown key to value.
     */
    public function __construct(
        public readonly string $name = '',
        #[UnknownPropertyCollector]
        public readonly array $additional = [],
    ) {
    }
}
```

Mapping `{ "name": "Ada", "age": "36", "city": "London" }` yields `$name = 'Ada'` and `$additional = ['age' => '36', 'city' => 'London']`. The collection is recursive: a nested typed child with its own collector captures the unknown keys at its level. Notes:

* The per-value conversion pipeline is bypassed, so the value is stored verbatim and the marked property's element type is deliberately open (`mixed`) — the consumer interprets the raw map itself.
* The property is only assigned when at least one unknown key is present, so it otherwise keeps its constructor default.
* The marked property must be array-typed. Declare at most one collector per class (a second raises an error). A source key that matches the collector property's name is mapped as that declared property, not collected.
* Key and value are both preserved verbatim. A configured name converter decides *whether* a key is unknown — `full_name` that camelises onto a declared `fullName` is mapped, not collected — but it does not rewrite a key that is unknown: `favourite_colour` is collected as `favourite_colour`, not `favouriteColour`. The collected map is therefore a faithful copy of the unmapped part of the payload and can be re-serialised as it arrived.
* As with ordinary mapping, two source keys that normalize to the same name collide, and the last one wins.

Test coverage: `tests/Attribute/UnknownPropertyCollectorTest.php`.

Attributes can be combined with PHPDoc annotations and work alongside the classic DocBlock metadata.
