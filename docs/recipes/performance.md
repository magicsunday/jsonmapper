# Performance hints

Type resolution is the most expensive part of a mapping run. Provide a PSR-6 cache pool to the constructor to reuse computed `Type` metadata.

## Caching type metadata

```php
require __DIR__ . '/vendor/autoload.php';

use MagicSunday\JsonMapper;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

// Assemble the reflection and PHPDoc extractors once.
$propertyInfo = new PropertyInfoExtractor(
    listExtractors: [new ReflectionExtractor()],
    typeExtractors: [new PhpDocExtractor()],
);
$propertyAccessor = PropertyAccess::createPropertyAccessor();

// Cache resolved Type metadata between mapping runs.
$cache = new ArrayAdapter();
$mapper = new JsonMapper($propertyInfo, $propertyAccessor, nameConverter: null, classMap: [], typeCache: $cache);
```

## General tips

- Reuse a single `JsonMapper` instance across requests to share cached metadata and registered handlers.
- Prefer `JsonMapper::createWithDefaults()` for simple setups — it already configures sensible defaults.
- Register type handlers once during bootstrap, not per mapping call.
