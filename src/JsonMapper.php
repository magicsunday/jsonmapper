<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday;

use function array_key_exists;
use Closure;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\AnnotationReader;
use DomainException;
use function in_array;
use InvalidArgumentException;
use function is_array;
use MagicSunday\JsonMapper\Annotation\ReplaceNullWithDefaultValue;
use MagicSunday\JsonMapper\Annotation\ReplaceProperty;
use MagicSunday\JsonMapper\Converter\PropertyNameConverterInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

/**
 * JsonMapper.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 *
 * @link    https://github.com/magicsunday/jsonmapper/
 *
 * @template TEntity
 * @template TEntityCollection
 */
class JsonMapper
{
    /**
     * @var PropertyInfoExtractorInterface
     */
    private $extractor;

    /**
     * @var PropertyAccessorInterface
     */
    private $accessor;

    /**
     * The property name converter instance.
     *
     * @var PropertyNameConverterInterface|null
     */
    protected $nameConverter;

    /**
     * Override class names that JsonMapper uses to create objects. Useful when your
     * setter methods accept abstract classes or interfaces.
     *
     * @var string[]|Closure[]
     */
    private $classMap;

    /**
     * The default value type instance.
     *
     * @var Type
     */
    private $defaultType;

    /**
     * The custom types.
     *
     * @var Closure[]
     */
    private $types = [];

    /**
     * JsonMapper constructor.
     *
     * @param PropertyInfoExtractorInterface      $extractor
     * @param PropertyAccessorInterface           $accessor
     * @param PropertyNameConverterInterface|null $nameConverter A name converter instance
     * @param string[]|Closure[]                  $classMap      A class map to override the class names
     */
    public function __construct(
        PropertyInfoExtractorInterface $extractor,
        PropertyAccessorInterface $accessor,
        ?PropertyNameConverterInterface $nameConverter = null,
        array $classMap = []
    ) {
        $this->extractor     = $extractor;
        $this->accessor      = $accessor;
        $this->nameConverter = $nameConverter;
        $this->defaultType   = new Type(Type::BUILTIN_TYPE_STRING);
        $this->classMap      = $classMap;
    }

    /**
     * Add a custom type.
     *
     * @param string  $type    The type name
     * @param Closure $closure The closure to execute for the defined type
     *
     * @return JsonMapper
     */
    public function addType(string $type, Closure $closure): JsonMapper
    {
        $this->types[$type] = $closure;

        return $this;
    }

    /**
     * Add a custom class map entry.
     *
     * @template T
     *
     * @param class-string<T> $className The name of the base class
     * @param Closure         $closure   The closure to execute if the base class was found
     *
     * @return JsonMapper
     */
    public function addCustomClassMapEntry(string $className, Closure $closure): JsonMapper
    {
        $this->classMap[$className] = $closure;

        return $this;
    }

    /**
     * Maps the JSON to the specified class entity.
     *
     * @param mixed                                $json                The JSON to map
     * @param class-string<TEntity>|null           $className           The class name of the initial element
     * @param class-string<TEntityCollection>|null $collectionClassName The class name of a collection used to assign
     *                                                                  the initial elements
     *
     * @phpstan-return ($collectionClassName is class-string
     *                      ? TEntityCollection
     *                      : ($className is class-string ? TEntity : null|mixed))
     *
     * @return mixed|TEntityCollection|TEntity|null
     *
     * @throws DomainException
     * @throws InvalidArgumentException
     */
    public function map($json, ?string $className = null, ?string $collectionClassName = null)
    {
        // Return plain JSON if no mapping classes are provided
        if ($className === null) {
            return $json;
        }

        // Map the original given class names to a custom ones
        $className = $this->getMappedClassName($className, $json);

        if ($collectionClassName !== null) {
            $collectionClassName = $this->getMappedClassName($collectionClassName, $json);
        }

        // Assert that the given classes exist
        $this->assertClassesExists($className, $collectionClassName);

        // Handle collections
        if ($this->isIterableWithArraysOrObjects($json)) {
            if ($collectionClassName !== null) {
                // Map arrays into collection class if given
                return $this->makeInstance(
                    $collectionClassName,
                    $this->asCollection(
                        $json,
                        new Type(Type::BUILTIN_TYPE_OBJECT, false, $className)
                    )
                );
            }

            // Handle plain array collections
            if ($this->isNumericIndexArray($json)) {
                // Map all elements of the JSON array to an array
                return $this->asCollection(
                    $json,
                    new Type(Type::BUILTIN_TYPE_OBJECT, false, $className)
                );
            }
        }

        $properties = $this->getProperties($className);
        $entity     = $this->makeInstance($className);

        // Return entity if JSON is not an array or object (is_iterable won't work here)
        if (!is_array($json) && !is_object($json)) {
            return $entity;
        }

        // Process all children

        /** @var string $propertyName */
        foreach ($json as $propertyName => $propertyValue) {
            // Replaces the property name with another one
            if ($this->isReplacePropertyAnnotation($className)) {
                $annotations = $this->extractClassAnnotations($className);

                foreach ($annotations as $annotation) {
                    if (
                        ($annotation instanceof ReplaceProperty)
                        && ($propertyName === $annotation->replaces)
                    ) {
                        /** @var string $propertyName */
                        $propertyName = $annotation->value;
                    }
                }
            }

            if ($this->nameConverter instanceof PropertyNameConverterInterface) {
                $propertyName = $this->nameConverter->convert($propertyName);
            }

            // Ignore all not defined properties
            if (!in_array($propertyName, $properties, true)) {
                continue;
            }

            $type  = $this->getType($className, $propertyName);
            $value = $this->getValue($propertyValue, $type);

            if (
                ($value === null)
                && $this->isReplaceNullWithDefaultValueAnnotation($className, $propertyName)
            ) {
                // Get the default value of the property
                $value = $this->getDefaultValue($className, $propertyName);
            }

            $this->setProperty($entity, $propertyName, $value);
        }

        return $entity;
    }

    /**
     * Creates an instance of the given class name. If a dependency injection container is provided,
     * it returns the instance for this.
     *
     * @template T
     *
     * @param class-string<T> $className               The class to instantiate
     * @param array|null      ...$constructorArguments The arguments of the constructor
     *
     * @return T
     */
    private function makeInstance(string $className, ?array ...$constructorArguments)
    {
        /** @var T $instance */
        $instance = new $className(...$constructorArguments);

        return $instance;
    }

    /**
     * Returns TRUE if the property contains an "ReplaceNullWithDefaultValue" annotation.
     *
     * @param class-string $className    The class name of the initial element
     * @param string       $propertyName The name of the property
     *
     * @return bool
     */
    private function isReplaceNullWithDefaultValueAnnotation(string $className, string $propertyName): bool
    {
        return $this->hasPropertyAnnotation(
            $className,
            $propertyName,
            ReplaceNullWithDefaultValue::class
        );
    }

    /**
     * Returns TRUE if the property contains an "ReplaceProperty" annotation.
     *
     * @param class-string $className The class name of the initial element
     *
     * @return bool
     */
    private function isReplacePropertyAnnotation(string $className): bool
    {
        return $this->hasClassAnnotation(
            $className,
            ReplaceProperty::class
        );
    }

    /**
     * Returns the specified reflection property.
     *
     * @param class-string $className    The class name of the initial element
     * @param string       $propertyName The name of the property
     *
     * @return ReflectionProperty|null
     */
    private function getReflectionProperty(string $className, string $propertyName): ?ReflectionProperty
    {
        try {
            return new ReflectionProperty($className, $propertyName);
        } catch (ReflectionException $exception) {
            return null;
        }
    }

    /**
     * Returns the specified reflection class.
     *
     * @param class-string $className The class name of the initial element
     *
     * @return ReflectionClass|null
     */
    private function getReflectionClass(string $className): ?ReflectionClass
    {
        if (!class_exists($className)) {
            return null;
        }

        return new ReflectionClass($className);
    }

    /**
     * Extracts possible property annotations.
     *
     * @param class-string $className    The class name of the initial element
     * @param string       $propertyName The name of the property
     *
     * @return Annotation[]|object[]
     */
    private function extractPropertyAnnotations(string $className, string $propertyName): array
    {
        $reflectionProperty = $this->getReflectionProperty($className, $propertyName);

        if ($reflectionProperty instanceof ReflectionProperty) {
            return (new AnnotationReader())
                ->getPropertyAnnotations($reflectionProperty);
        }

        return [];
    }

    /**
     * Extracts possible class annotations.
     *
     * @param class-string $className The class name of the initial element
     *
     * @return Annotation[]|object[]
     */
    private function extractClassAnnotations(string $className): array
    {
        $reflectionClass = $this->getReflectionClass($className);

        if ($reflectionClass instanceof ReflectionClass) {
            return (new AnnotationReader())
                ->getClassAnnotations($reflectionClass);
        }

        return [];
    }

    /**
     * Returns TRUE if the property has the given annotation.
     *
     * @param class-string $className      The class name of the initial element
     * @param string       $propertyName   The name of the property
     * @param string       $annotationName The name of the property annotation
     *
     * @return bool
     */
    private function hasPropertyAnnotation(string $className, string $propertyName, string $annotationName): bool
    {
        $annotations = $this->extractPropertyAnnotations($className, $propertyName);

        foreach ($annotations as $annotation) {
            if ($annotation instanceof $annotationName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns TRUE if the class has the given annotation.
     *
     * @param class-string $className      The class name of the initial element
     * @param string       $annotationName The name of the class annotation
     *
     * @return bool
     */
    private function hasClassAnnotation(string $className, string $annotationName): bool
    {
        $annotations = $this->extractClassAnnotations($className);

        foreach ($annotations as $annotation) {
            if ($annotation instanceof $annotationName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts the default value of a property.
     *
     * @param class-string $className    The class name of the initial element
     * @param string       $propertyName The name of the property
     *
     * @return mixed|null
     */
    private function getDefaultValue(string $className, string $propertyName)
    {
        $reflectionProperty = $this->getReflectionProperty($className, $propertyName);

        if (!($reflectionProperty instanceof ReflectionProperty)) {
            return null;
        }

        return $reflectionProperty->getDeclaringClass()->getDefaultProperties()[$propertyName] ?? null;
    }

    /**
     * Returns TRUE if the given json contains integer property keys.
     *
     * @param mixed $json
     *
     * @return bool
     */
    private function isNumericIndexArray($json): bool
    {
        foreach ($json as $propertyName => $propertyValue) {
            if (is_int($propertyName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns TRUE if the given json is a plain array or object.
     *
     * @param mixed $json
     *
     * @return bool
     */
    private function isIterableWithArraysOrObjects($json): bool
    {
        // Return false if JSON is not an array or object (is_iterable won't work here)
        if (!is_array($json) && !is_object($json)) {
            return false;
        }

        foreach ($json as $propertyValue) {
            if (is_array($propertyValue)) {
                continue;
            }

            if (is_object($propertyValue)) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Assert that the given classes exist.
     *
     * @param class-string      $className           The class name of the initial element
     * @param class-string|null $collectionClassName The class name of a collection used to
     *                                               assign the initial elements
     *
     * @throws InvalidArgumentException
     */
    private function assertClassesExists(string $className, ?string $collectionClassName = null): void
    {
        if (!class_exists($className)) {
            throw new InvalidArgumentException(sprintf('Class [%s] does not exist', $className));
        }

        if ($collectionClassName === null) {
            return;
        }

        if (class_exists($collectionClassName)) {
            return;
        }

        throw new InvalidArgumentException(sprintf('Class [%s] does not exist', $collectionClassName));
    }

    /**
     * Sets a property value.
     *
     * @param object $entity
     * @param string $name
     * @param mixed  $value
     */
    private function setProperty(object $entity, string $name, $value): void
    {
        // Handle variadic setters
        if (is_array($value)) {
            $methodName = 'set' . ucfirst($name);

            if (method_exists($entity, $methodName)) {
                $method     = new ReflectionMethod($entity, $methodName);
                $parameters = $method->getParameters();

                if ((count($parameters) === 1) && $parameters[0]->isVariadic()) {
                    $callable = [$entity, $methodName];

                    if (is_callable($callable)) {
                        call_user_func_array($callable, $value);
                    }

                    return;
                }
            }
        }

        $this->accessor->setValue($entity, $name, $value);
    }

    /**
     * Get all public properties for the specified class.
     *
     * @param string $className The name of the class used to extract the properties
     *
     * @return string[]
     */
    private function getProperties(string $className): array
    {
        return $this->extractor->getProperties($className) ?? [];
    }

    /**
     * Determine the type for the specified property using reflection.
     *
     * @param string $className    The name of the class used to extract the property type info
     * @param string $propertyName The name of the property
     *
     * @return Type
     */
    private function getType(string $className, string $propertyName): Type
    {
        return $this->extractor->getTypes($className, $propertyName)[0] ?? $this->defaultType;
    }

    /**
     * Get the value for the specified node.
     *
     * @param mixed $json
     * @param Type  $type
     *
     * @return mixed|null
     *
     * @throws DomainException
     */
    private function getValue($json, Type $type)
    {
        if ((is_array($json) || is_object($json)) && $type->isCollection()) {
            $collectionType = $this->getCollectionValueType($type);
            $collection     = $this->asCollection($json, $collectionType);

            // Create a new instance of the collection class
            if ($type->getBuiltinType() === Type::BUILTIN_TYPE_OBJECT) {
                return $this->makeInstance(
                    $this->getClassName($json, $type),
                    $collection
                );
            }

            return $collection;
        }

        // Ignore empty values
        if ($json === null) {
            return null;
        }

        $builtinType = $type->getBuiltinType();

        if ($builtinType === Type::BUILTIN_TYPE_OBJECT) {
            return $this->asObject($json, $type);
        }

        settype($json, $builtinType);

        return $json;
    }

    /**
     * Gets collection value type.
     *
     * @param Type $type
     *
     * @return Type
     */
    public function getCollectionValueType(Type $type): Type
    {
        $collectionValueType = $type->getCollectionValueTypes()[0] ?? null;

        return $collectionValueType ?? $this->defaultType;
    }

    /**
     * Returns the class name of the given object type.
     *
     * @param Type $type
     *
     * @return class-string
     *
     * @throws DomainException
     */
    private function getClassNameFromType(Type $type): string
    {
        /** @var class-string|null $className */
        $className = $type->getClassName();

        // @codeCoverageIgnoreStart
        if ($className === null) {
            // This should never happen
            throw new DomainException('Type has no valid class name');
        }

        // @codeCoverageIgnoreEnd

        return $className;
    }

    /**
     * Returns the mapped class name.
     *
     * @param class-string $className The class name to be mapped using the class map
     * @param mixed        $json      The JSON data
     *
     * @return class-string
     *
     * @throws DomainException
     */
    private function getMappedClassName(string $className, $json): string
    {
        if (array_key_exists($className, $this->classMap)) {
            $classNameOrClosure = $this->classMap[$className];

            if (!($classNameOrClosure instanceof Closure)) {
                /** @var class-string $classNameOrClosure */
                return $classNameOrClosure;
            }

            // Execute closure to get the mapped class name
            $className = $classNameOrClosure($json);
        }

        /** @var class-string $className */
        return $className;
    }

    /**
     * Returns the class name.
     *
     * @param mixed $json
     * @param Type  $type
     *
     * @return class-string
     *
     * @throws DomainException
     */
    private function getClassName($json, Type $type): string
    {
        return $this->getMappedClassName(
            $this->getClassNameFromType($type),
            $json
        );
    }

    /**
     * Cast node to a collection.
     *
     * @param mixed $json
     * @param Type  $type
     *
     * @return mixed[]|null
     *
     * @throws DomainException
     */
    private function asCollection($json, Type $type): ?array
    {
        if ($json === null) {
            return null;
        }

        $collection = [];

        foreach ($json as $key => $value) {
            $collection[$key] = $this->getValue($value, $type);
        }

        return $collection;
    }

    /**
     * Cast node to object.
     *
     * @param mixed $json
     * @param Type  $type
     *
     * @return mixed|null
     *
     * @throws DomainException
     */
    private function asObject($json, Type $type)
    {
        /** @var class-string<TEntity> $className */
        $className = $this->getClassName($json, $type);

        if ($this->isCustomType($className)) {
            return $this->callCustomClosure($json, $className);
        }

        return $this->map($json, $className);
    }

    /**
     * Determine if the specified type is a custom type.
     *
     * @template T
     *
     * @param class-string<T> $typeClassName
     *
     * @return bool
     */
    private function isCustomType(string $typeClassName): bool
    {
        return array_key_exists($typeClassName, $this->types);
    }

    /**
     * Call the custom closure for the specified type.
     *
     * @template T
     *
     * @param mixed           $json
     * @param class-string<T> $typeClassName
     *
     * @return mixed
     */
    private function callCustomClosure($json, string $typeClassName)
    {
        $callback = $this->types[$typeClassName];

        return $callback($json);
    }
}
