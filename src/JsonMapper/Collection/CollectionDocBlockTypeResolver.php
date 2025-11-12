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
use Symfony\Component\TypeInfo\Type\CollectionType;

/**
 * Resolves collection value types from PHPDoc annotations on collection classes.
 */
final class CollectionDocBlockTypeResolver
{
    private DocBlockFactoryInterface $docBlockFactory;

    private ContextFactory $contextFactory;

    private PhpDocTypeHelper $phpDocTypeHelper;

    public function __construct(
        ?DocBlockFactoryInterface $docBlockFactory = null,
        ?ContextFactory $contextFactory = null,
        ?PhpDocTypeHelper $phpDocTypeHelper = null,
    ) {
        if (!class_exists(DocBlockFactory::class)) {
            throw new LogicException(
                sprintf(
                    'Unable to use %s without the "phpdocumentor/reflection-docblock" package. Please run "composer require phpdocumentor/reflection-docblock".',
                    self::class,
                ),
            );
        }

        $this->docBlockFactory  = $docBlockFactory ?? DocBlockFactory::createInstance();
        $this->contextFactory   = $contextFactory ?? new ContextFactory();
        $this->phpDocTypeHelper = $phpDocTypeHelper ?? new PhpDocTypeHelper();
    }

    /**
     * Attempts to resolve a {@see CollectionType} from the collection class PHPDoc.
     *
     * @param class-string $collectionClassName
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
