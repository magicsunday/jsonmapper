<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Value\Strategy;

use Closure;
use MagicSunday\JsonMapper\Context\MappingContext;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\UnionType;

use function assert;

/**
 * Resolves a value against a union type.
 *
 * Union resolution used to live on the property path alone, reachable only through
 * JsonMapper::convertValue(). Anything converting a value another way - a collection converting its
 * elements being the case that mattered - matched no strategy for a union element type and handed
 * the raw payload back unconverted, without recording anything. Registering the resolution as a
 * strategy puts it where every consumer of the converter reaches it.
 *
 * @internal This is not a public extension point. Register conversions through
 *           {@see \MagicSunday\JsonMapper\Value\TypeHandlerInterface} via JsonMapper::addTypeHandler().
 */
final readonly class UnionValueConversionStrategy implements ValueConversionStrategyInterface
{
    /**
     * Creates the strategy with the resolution it delegates to.
     *
     * @param Closure(mixed, UnionType<Type>, MappingContext):mixed $resolver Resolution shared with the property path.
     */
    public function __construct(
        private Closure $resolver,
    ) {
    }

    /**
     * Determines whether the supplied type is a union.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return bool TRUE when the target type is a union type.
     */
    public function supports(Type $type, mixed $value, MappingContext $context): bool
    {
        return $type instanceof UnionType;
    }

    /**
     * Resolves the value against the union members.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return mixed Value converted to whichever member accepted it.
     */
    public function convert(Type $type, mixed $value, MappingContext $context): mixed
    {
        assert($type instanceof UnionType);

        return ($this->resolver)($value, $type, $context);
    }
}
