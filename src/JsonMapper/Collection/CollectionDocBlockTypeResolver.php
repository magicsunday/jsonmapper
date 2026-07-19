<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Collection;

use InvalidArgumentException;
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
use Symfony\Component\TypeInfo\Type\TemplateType;
use Symfony\Component\TypeInfo\TypeIdentifier;

use function array_key_exists;
use function interface_exists;
use function sprintf;

/**
 * Resolves collection value types from PHPDoc annotations on collection classes.
 *
 * @phpstan-type CollectionWrappedType BuiltinType<TypeIdentifier::ARRAY>|BuiltinType<TypeIdentifier::ITERABLE>|ObjectType<class-string>
 */
final class CollectionDocBlockTypeResolver
{
    private DocBlockFactoryInterface $docBlockFactory;

    /**
     * Resolutions already performed, keyed by class name, with null meaning "no collection type".
     *
     * The class is otherwise stateless, and this does not change that in any observable way: the
     * answer for a class name is fixed for the lifetime of the process, so the memo is a pure
     * function's cache rather than accumulated state.
     *
     * It is not an optimisation on speculation. Resolving costs about 308 microseconds per class -
     * ContextFactory reads and tokenises the class file on every call, with no cache anywhere in
     * that path - against roughly 0.3 microseconds for the guards that precede it. Without the
     * memo, mapping 5000 elements whose classes carry an ordinary docblock more than doubled.
     *
     * @var array<class-string, CollectionType<CollectionWrappedType|GenericType<CollectionWrappedType>>|null>
     */
    private array $resolved = [];

    /**
     * @param DocBlockFactoryInterface|null $docBlockFactory  Optional docblock factory used to parse collection annotations.
     * @param ContextFactory                $contextFactory   Factory for building type resolution contexts for reflected classes.
     * @param PhpDocTypeHelper              $phpDocTypeHelper Helper translating DocBlock types into Symfony TypeInfo representations.
     */
    public function __construct(
        ?DocBlockFactoryInterface $docBlockFactory = null,
        private readonly ContextFactory $contextFactory = new ContextFactory(),
        private readonly PhpDocTypeHelper $phpDocTypeHelper = new PhpDocTypeHelper(),
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
     * @param class-string $collectionClassName Fully qualified class name of the collection wrapper to inspect.
     *
     * @return CollectionType<CollectionWrappedType|GenericType<CollectionWrappedType>>|null Resolved collection metadata or null when no matching PHPDoc is available.
     */
    public function resolve(string $collectionClassName): ?CollectionType
    {
        if (array_key_exists($collectionClassName, $this->resolved)) {
            return $this->resolved[$collectionClassName];
        }

        return $this->resolved[$collectionClassName] = $this->readFromDocBlock($collectionClassName);
    }

    /**
     * Resolves the collection type or explains what the class is missing.
     *
     * Both entry points into collection mapping need the same two checks and the same guidance,
     * so they live here rather than being restated at each call site: a message improved in one
     * place and not the other is how the two drift apart.
     *
     * @param class-string $collectionClassName Fully qualified class name of the collection wrapper to inspect.
     *
     * @return CollectionType<CollectionWrappedType|GenericType<CollectionWrappedType>> Resolved collection metadata
     *
     * @throws InvalidArgumentException When the class declares no element type, or only a template parameter.
     */
    public function resolveOrFail(string $collectionClassName): CollectionType
    {
        $collectionType = $this->resolve($collectionClassName);

        if (!$collectionType instanceof CollectionType) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unable to resolve the element type for collection [%s]. Define an "@extends" annotation such as "@extends %s<YourClass>".',
                    $collectionClassName,
                    $collectionClassName,
                )
            );
        }

        $valueType = $collectionType->getCollectionValueType();

        // A template parameter does not survive as a TemplateType here: the docblock helper
        // resolves "@extends ArrayObject<int, T>" to an ObjectType naming a class T in the
        // declaring namespace, which simply does not exist. Testing for TemplateType alone
        // therefore never fired, and the unusable element type reached the factory and failed
        // there on a message naming neither the annotation nor the fix. Both forms are checked.
        // interface_exists() as well as class_exists(): a collection of an interface is the
        // ordinary shape for a polymorphic list, resolved through a class map at mapping time.
        // Testing only for a class would refuse it here, before the map ever gets a say.
        $namesUnknownClass = ($valueType instanceof ObjectType)
            && !class_exists($valueType->getClassName())
            && !interface_exists($valueType->getClassName());

        if (($valueType instanceof TemplateType) || $namesUnknownClass) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unable to resolve the element type for collection [%s]. Please provide a concrete class in the "@extends" annotation.',
                    $collectionClassName,
                )
            );
        }

        return $collectionType;
    }

    /**
     * Reads the collection type from the class PHPDoc.
     *
     * @param class-string $collectionClassName Fully qualified class name of the collection wrapper to inspect.
     *
     * @return CollectionType<CollectionWrappedType|GenericType<CollectionWrappedType>>|null Resolved collection metadata or null when no matching PHPDoc is available.
     */
    private function readFromDocBlock(string $collectionClassName): ?CollectionType
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
