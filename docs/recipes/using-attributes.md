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

Both `first_name` and `name` keys populate the `$fullName` property. Declare one attribute per alias to express the precedence order explicitly.

Test coverage: `tests/Attribute/ReplacePropertyTest.php::replaceProperty`.

Attributes can be combined with PHPDoc annotations and work alongside the classic DocBlock metadata.
