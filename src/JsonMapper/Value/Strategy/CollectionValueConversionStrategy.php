<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Value\Strategy;

use MagicSunday\JsonMapper\Collection\CollectionFactoryInterface;
use MagicSunday\JsonMapper\Context\MappingContext;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;

use function assert;

/**
 * Converts collection values using the configured factory.
 */
final readonly class CollectionValueConversionStrategy implements ValueConversionStrategyInterface
{
    /**
     * Creates the strategy with the provided collection factory.
     *
     * @param CollectionFactoryInterface<array-key, mixed> $collectionFactory Factory responsible for instantiating collections during conversion.
     */
    public function __construct(
        private CollectionFactoryInterface $collectionFactory,
    ) {
    }

    /**
     * Determines whether the supplied type represents a collection.
     *
     * @param mixed          $value   Raw value coming from the input payload.
     * @param Type           $type    Type metadata describing the target property.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return bool TRUE when the target type is a collection type.
     */
    public function supports(mixed $value, Type $type, MappingContext $context): bool
    {
        return $type instanceof CollectionType;
    }

    /**
     * Converts the JSON value into a collection instance.
     *
     * @param mixed          $value   Raw value coming from the input payload.
     * @param Type           $type    Type metadata describing the target property.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return mixed Collection created by the factory based on the type metadata.
     */
    public function convert(mixed $value, Type $type, MappingContext $context): mixed
    {
        assert($type instanceof CollectionType);

        return $this->collectionFactory->fromCollectionType($type, $value, $context);
    }
}
