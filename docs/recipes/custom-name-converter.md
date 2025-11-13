# Using a custom name converter

Property name converters translate JSON keys to PHP property names. JsonMapper provides `CamelCasePropertyNameConverter` out of the box and allows you to supply your own implementation of `PropertyNameConverterInterface`.

```php
use MagicSunday\JsonMapper\Converter\PropertyNameConverterInterface;

final class UpperSnakeCaseConverter implements PropertyNameConverterInterface
{
    public function convert(string $name): string
    {
        return strtolower(str_replace('_', '', $name));
    }
}
```

```php
use MagicSunday\JsonMapper\Converter\CamelCasePropertyNameConverter;
use MagicSunday\JsonMapper\JsonMapper;

$propertyInfo = /* PropertyInfoExtractorInterface */;
$propertyAccessor = /* PropertyAccessorInterface */;
$converter = new CamelCasePropertyNameConverter();
// or $converter = new UpperSnakeCaseConverter();

$mapper = new JsonMapper($propertyInfo, $propertyAccessor, $converter);
```

Name converters are stateless and should be declared `final`. They are applied to every property access during mapping, so keep the implementation idempotent and efficient.
