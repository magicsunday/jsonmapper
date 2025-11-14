# Using a custom name converter

Property name converters translate JSON keys to PHP property names. JsonMapper provides `CamelCasePropertyNameConverter` out of the box and allows you to supply your own implementation of `PropertyNameConverterInterface`.

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
use MagicSunday\JsonMapper\Converter\CamelCasePropertyNameConverter;
use MagicSunday\JsonMapper\JsonMapper;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

// Collect metadata and choose the converter implementation.
$propertyInfo = new PropertyInfoExtractor(
    listExtractors: [new ReflectionExtractor()],
    typeExtractors: [new PhpDocExtractor()],
);
$propertyAccessor = PropertyAccess::createPropertyAccessor();
$converter = new CamelCasePropertyNameConverter();
// or $converter = new UpperSnakeCaseConverter();

$mapper = new JsonMapper($propertyInfo, $propertyAccessor, $converter);
```

Name converters are stateless and should be declared `final`. They are applied to every property access during mapping, so keep the implementation idempotent and efficient.
