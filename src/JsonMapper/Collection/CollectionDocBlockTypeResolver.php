<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Collection;

use LogicException;
use phpDocumentor\Reflection\DocBlock\Tags\TagWithType;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use phpDocumentor\Reflection\Type as DocType;
use phpDocumentor\Reflection\Types\ContextFactory;
use ReflectionClass;
use Symfony\Component\PropertyInfo\Util\PhpDocTypeHelper;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Resolves collection value types from PHPDoc annotations on collection classes.
 *
 * @phpstan-type CollectionWrappedType BuiltinType<TypeIdentifier::ARRAY>|BuiltinType<TypeIdentifier::ITERABLE>|ObjectType<class-string>
 */
final class CollectionDocBlockTypeResolver
{
    private DocBlockFactoryInterface $docBlockFactory;

    public function __construct(
        ?DocBlockFactoryInterface $docBlockFactory = null,
        private ContextFactory $contextFactory = new ContextFactory(),
        private PhpDocTypeHelper $phpDocTypeHelper = new PhpDocTypeHelper(),
    ) {
        if (!class_exists(DocBlockFactory::class)) {
            throw new LogicException(
                sprintf(
                    'Unable to use %s without the "phpdocumentor/reflection-docblock" package. Please run "composer require phpdocumentor/reflection-docblock".',
                    self::class,
                ),
            );
        }

        $this->docBlockFactory = $docBlockFactory ?? DocBlockFactory::createInstance();
    }

    /**
     * Attempts to resolve a {@see CollectionType} from the collection class PHPDoc.
     *
     * @param class-string $collectionClassName
     *
     * @return CollectionType<CollectionWrappedType|GenericType<CollectionWrappedType>>|null
     */
    public function resolve(string $collectionClassName): ?CollectionType
    {
        $reflectionClass = new ReflectionClass($collectionClassName);
        $docComment      = $reflectionClass->getDocComment();

        if ($docComment === false) {
            return null;
        }

        $context  = $this->contextFactory->createFromReflector($reflectionClass);
        $docBlock = $this->docBlockFactory->create($docComment, $context);

        foreach (['extends', 'implements'] as $tagName) {
            foreach ($docBlock->getTagsByName($tagName) as $tag) {
                if (!$tag instanceof TagWithType) {
                    continue;
                }

                $type = $tag->getType();

                if (!$type instanceof DocType) {
                    continue;
                }

                $resolved = $this->phpDocTypeHelper->getType($type);

                if ($resolved instanceof CollectionType) {
                    return $resolved;
                }
            }
        }

        return null;
    }
}
