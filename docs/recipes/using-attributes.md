# Using mapper attributes

JsonMapper ships with attributes that can refine how JSON data is mapped to PHP objects.

## `ReplaceNullWithDefaultValue`
Use this attribute on properties that should fall back to their default value when the JSON payload explicitly contains `null`.

```php
use MagicSunday\JsonMapper\Attribute\ReplaceNullWithDefaultValue;

final class User
{
    #[ReplaceNullWithDefaultValue]
    public array $roles = [];
}
```

When a payload contains `{ "roles": null }`, the mapper keeps the default empty array.

## `ReplaceProperty`
Apply this attribute at class level to redirect one or more incoming property names to a different target property.

```php
use MagicSunday\JsonMapper\Attribute\ReplaceProperty;

#[ReplaceProperty('fullName', replaces: ['first_name', 'name'])]
final class Contact
{
    public string $fullName;
}
```

Both `first_name` and `name` keys will populate the `$fullName` property. Order matters: the first matching alias wins.

Attributes can be combined with PHPDoc annotations and work alongside the classic DocBlock metadata.
