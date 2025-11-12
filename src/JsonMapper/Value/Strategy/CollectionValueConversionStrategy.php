<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Value\Strategy;

use MagicSunday\JsonMapper\Collection\CollectionFactory;
use MagicSunday\JsonMapper\Context\MappingContext;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;

use function is_array;
use function is_object;

/**
 * Converts collection values using the configured factory.
 */
final class CollectionValueConversionStrategy implements ValueConversionStrategyInterface
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
    ) {
    }

    public function supports(mixed $value, Type $type, MappingContext $context): bool
    {
        return ($type instanceof CollectionType) && (is_array($value) || is_object($value) || $value === null);
    }

    public function convert(mixed $value, Type $type, MappingContext $context): mixed
    {
        \assert($type instanceof CollectionType);

        return $this->collectionFactory->fromCollectionType($type, $value, $context);
    }
}
