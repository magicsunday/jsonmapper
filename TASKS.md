# Follow-up tasks

## PHPStan maximum-level review (composer ci:test:php:phpstan)

### MagicSunday\\JsonMapper
- [x] Resolve PHPStan: `Property MagicSunday\\JsonMapper::$collectionFactory with generic interface MagicSunday\\JsonMapper\\Collection\\CollectionFactoryInterface does not specify its types: TKey, TValue` (`src/JsonMapper.php:101`).
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper::convertUnionValue() has parameter $type with generic class Symfony\\Component\\TypeInfo\\Type\\UnionType but does not specify its types: T` (`src/JsonMapper.php:497`).
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper::describeUnionType() has parameter $type with generic class Symfony\\Component\\TypeInfo\\Type\\UnionType but does not specify its types: T` (`src/JsonMapper.php:586`).
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper::unionAllowsNull() has parameter $type with generic class Symfony\\Component\\TypeInfo\\Type\\UnionType but does not specify its types: T` (`src/JsonMapper.php:597`).
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper::getReflectionClass() return type with generic class ReflectionClass does not specify its types: T` (`src/JsonMapper.php:735`).

### MagicSunday\\JsonMapper\\Collection\\CollectionDocBlockTypeResolver
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper\\Collection\\CollectionDocBlockTypeResolver::resolve() return type with generic class Symfony\\Component\\TypeInfo\\Type\\CollectionType does not specify its types: T` (`src/JsonMapper/Collection/CollectionDocBlockTypeResolver.php:53`).

### MagicSunday\\JsonMapper\\Collection\\CollectionFactory
- [x] Resolve PHPStan: `Class MagicSunday\\JsonMapper\\Collection\\CollectionFactory implements generic interface MagicSunday\\JsonMapper\\Collection\\CollectionFactoryInterface but does not specify its types: TKey, TValue` (`src/JsonMapper/Collection/CollectionFactory.php:35`).
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper\\Collection\\CollectionFactory::fromCollectionType() has parameter $type with generic class Symfony\\Component\\TypeInfo\\Type\\CollectionType but does not specify its types: T` (`src/JsonMapper/Collection/CollectionFactory.php:94`).
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper\\Collection\\CollectionFactory::resolveWrappedClass() has parameter $objectType with generic class Symfony\\Component\\TypeInfo\\Type\\ObjectType but does not specify its types: T` (`src/JsonMapper/Collection/CollectionFactory.php:120`).

### MagicSunday\\JsonMapper\\Collection\\CollectionFactoryInterface
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper\\Collection\\CollectionFactoryInterface::fromCollectionType() has parameter $type with generic class Symfony\\Component\\TypeInfo\\Type\\CollectionType but does not specify its types: T` (`src/JsonMapper/Collection/CollectionFactoryInterface.php:42`).

### MagicSunday\\JsonMapper\\Type\\TypeResolver
- [x] Resolve PHPStan: `Property MagicSunday\\JsonMapper\\Type\\TypeResolver::$defaultType with generic class Symfony\\Component\\TypeInfo\\Type\\BuiltinType does not specify its types: T` (`src/JsonMapper/Type/TypeResolver.php:33`).
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper\\Type\\TypeResolver::normalizeUnionType() has parameter $type with generic class Symfony\\Component\\TypeInfo\\Type\\UnionType but does not specify its types: T` (`src/JsonMapper/Type/TypeResolver.php:224`).

### MagicSunday\\JsonMapper\\Value\\Strategy\\BuiltinValueConversionStrategy
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper\\Value\\Strategy\\BuiltinValueConversionStrategy::normalizeValue() has parameter $type with generic class Symfony\\Component\\TypeInfo\\Type\\BuiltinType but does not specify its types: T` (`src/JsonMapper/Value/Strategy/BuiltinValueConversionStrategy.php:66`).
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper\\Value\\Strategy\\BuiltinValueConversionStrategy::guardCompatibility() has parameter $type with generic class Symfony\\Component\\TypeInfo\\Type\\BuiltinType but does not specify its types: T` (`src/JsonMapper/Value/Strategy/BuiltinValueConversionStrategy.php:125`).
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper\\Value\\Strategy\\BuiltinValueConversionStrategy::allowsNull() has parameter $type with generic class Symfony\\Component\\TypeInfo\\Type\\BuiltinType but does not specify its types: T` (`src/JsonMapper/Value/Strategy/BuiltinValueConversionStrategy.php:156`).

### MagicSunday\\JsonMapper\\Value\\Strategy\\CollectionValueConversionStrategy
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper\\Value\\Strategy\\CollectionValueConversionStrategy::__construct() has parameter $collectionFactory with generic interface MagicSunday\\JsonMapper\\Collection\\CollectionFactoryInterface but does not specify its types: TKey, TValue` (`src/JsonMapper/Value/Strategy/CollectionValueConversionStrategy.php:26`).

### MagicSunday\\JsonMapper\\Value\\Strategy\\DateTimeValueConversionStrategy
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper\\Value\\Strategy\\DateTimeValueConversionStrategy::extractObjectType() return type with generic class Symfony\\Component\\TypeInfo\\Type\\ObjectType does not specify its types: T` (`src/JsonMapper/Value/Strategy/ObjectTypeConversionGuardTrait.php:27`).
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper\\Value\\Strategy\\DateTimeValueConversionStrategy::guardNullableValue() has parameter $type with generic class Symfony\\Component\\TypeInfo\\Type\\ObjectType but does not specify its types: T` (`src/JsonMapper/Value/Strategy/ObjectTypeConversionGuardTrait.php:43`).

### MagicSunday\\JsonMapper\\Value\\Strategy\\EnumValueConversionStrategy
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper\\Value\\Strategy\\EnumValueConversionStrategy::extractObjectType() return type with generic class Symfony\\Component\\TypeInfo\\Type\\ObjectType does not specify its types: T` (`src/JsonMapper/Value/Strategy/ObjectTypeConversionGuardTrait.php:27`).
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper\\Value\\Strategy\\EnumValueConversionStrategy::guardNullableValue() has parameter $type with generic class Symfony\\Component\\TypeInfo\\Type\\ObjectType but does not specify its types: T` (`src/JsonMapper/Value/Strategy/ObjectTypeConversionGuardTrait.php:43`).

### MagicSunday\\JsonMapper\\Value\\Strategy\\ObjectValueConversionStrategy
- [x] Resolve PHPStan: `Method MagicSunday\\JsonMapper\\Value\\Strategy\\ObjectValueConversionStrategy::resolveClassName() has parameter $type with generic class Symfony\\Component\\TypeInfo\\Type\\ObjectType but does not specify its types: T` (`src/JsonMapper/Value/Strategy/ObjectValueConversionStrategy.php:73`).

### MagicSunday\\Test\\Classes\\Base
- [x] Resolve PHPStan: `Property MagicSunday\\Test\\Classes\\Base::$simpleCollection with generic class MagicSunday\\Test\\Classes\\Collection does not specify its types: TKey, TValue` (`tests/Classes/Base.php:54`).

### MagicSunday\\Test\\Classes\\ClassMap\\CollectionSource
- [x] Resolve PHPStan: `Class MagicSunday\\Test\\Classes\\ClassMap\\CollectionSource extends generic class MagicSunday\\Test\\Classes\\Collection but does not specify its types: TKey, TValue` (`tests/Classes/ClassMap/CollectionSource.php:23`).

### MagicSunday\\Test\\Classes\\ClassMap\\CollectionTarget
- [x] Resolve PHPStan: `Class MagicSunday\\Test\\Classes\\ClassMap\\CollectionTarget extends generic class ArrayObject but does not specify its types: TKey, TValue` (`tests/Classes/ClassMap/CollectionTarget.php:23`).
