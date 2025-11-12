<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Type;

use MagicSunday\JsonMapper\Context\MappingContext;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Resolves property types using Symfony's PropertyInfo component.
 */
final class TypeResolver
{
    private BuiltinType $defaultType;

    public function __construct(
        private readonly PropertyInfoExtractorInterface $extractor,
    ) {
        $this->defaultType = new BuiltinType(TypeIdentifier::STRING);
    }

    /**
     * Resolves the declared type for the provided property.
     */
    public function resolve(string $className, string $propertyName, MappingContext $context): Type
    {
        $type = $this->extractor->getType($className, $propertyName);

        if ($type instanceof UnionType) {
            return $type->getTypes()[0];
        }

        return $type ?? $this->defaultType;
    }
}
