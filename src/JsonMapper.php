<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday;

use Closure;
use DomainException;
use InvalidArgumentException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

use function array_key_exists;
use function in_array;

/**
 * JsonMapper
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class JsonMapper
{
    /**
     * @var PropertyInfoExtractorInterface
     */
    private PropertyInfoExtractorInterface $extractor;

    /**
     * @var PropertyAccessorInterface
     */
    private PropertyAccessorInterface $accessor;

    /**
     * The property name converter instance.
     *
     * @var null|PropertyNameConverterInterface
     */
    protected ?PropertyNameConverterInterface $nameConverter = null;

    /**
     * Override class names that JsonMapper uses to create objects. Useful when your
     * setter methods accept abstract classes or interfaces.
     *
     * @var string[]|Closure[]
     */
    private array $classMap;

    /**
     * The default value type instance.
     *
     * @var Type
     */
    private Type $defaultType;

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
     * @param null|PropertyNameConverterInterface $nameConverter A name converter instance
     * @param string[]|Closure[]                  $classMap      A class map to override the class names
     */
    public function __construct(
        PropertyInfoExtractorInterface $extractor,
        PropertyAccessorInterface $accessor,
        PropertyNameConverterInterface $nameConverter = null,
        array $classMap = []
    ) {
        $this->extractor     = $extractor;
        $this->accessor      = $accessor;
        $this->nameConverter = $nameConverter;
        $this->defaultType   = new Type('string');
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
     * @param string  $className The name of the base class
     * @param Closure $closure   The closure to execute if the base class was found
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
     * @param mixed       $json                The JSON to map
     * @param string      $className           The class name of the initial element
     * @param null|string $collectionClassName The class name of a collection used to assign the initial elements
     *
     * @return null|object|object[]
     *
     * @throws InvalidArgumentException
     * @throws DomainException
     */
    public function map($json, string $className, string $collectionClassName = null)
    {
        $this->assertClassesExists($className, $collectionClassName);

        if ($collectionClassName) {
            // Map all elements of the JSON array to this collection
            return $this->makeInstance(
                $collectionClassName,
                $this->asCollection(
                    $json,
                    new Type('object', false, $className, false)
                )
            );
        }

        $properties = $this->getProperties($className);
        $entity     = $this->makeInstance($className);

        // Process all children
        foreach ($json as $propertyName => $propertyValue) {
            if ($this->nameConverter) {
                $propertyName = $this->nameConverter->convert($propertyName);
            }

            // Ignore all not defined properties
            if (in_array($propertyName, $properties, true)) {
                $type  = $this->getType($className, $propertyName);
                $value = $this->getValue($propertyValue, $type);

                $this->setProperty($entity, $propertyName, $value);
            }
        }

        return $entity;
    }

    /**
     * Assert that the given classes exists.
     *
     * @param string      $className           The class name of the initial element
     * @param null|string $collectionClassName The class name of a collection used to assign the initial elements
     *
     * @throws InvalidArgumentException
     */
    private function assertClassesExists(string $className, string $collectionClassName = null): void
    {
        if (!class_exists($className)) {
            throw new InvalidArgumentException("Class [$className] does not exist");
        }

        if ($collectionClassName && !class_exists($collectionClassName)) {
            throw new InvalidArgumentException("Class [$collectionClassName] does not exist");
        }
    }

    /**
     * Creates an instance of the given class name. If a dependency injection container is provided,
     * it returns the instance for this.
     *
     * @param string|Closure $className                The class to instantiate
     * @param mixed          $constructorArguments,... The arguments for the constructor
     *
     * @return object
     */
    private function makeInstance($className, ...$constructorArguments): object
    {
        return new $className(...$constructorArguments);
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
     * @return null|array|bool|float|mixed
     *
     * @throws DomainException
     */
    private function getValue($json, Type $type)
    {
        if ($type->isCollection()) {
            $collectionType = $type->getCollectionValueType() ?? $this->defaultType;
            $collection     = $this->asCollection($json, $collectionType);

            // Create a new instance of the collection class
            if ($type->getBuiltinType() === 'object') {
                return $this->makeInstance(
                    $this->getClassName($type),
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

        if ($builtinType === 'object') {
            return $this->asObject($json, $type);
        }

        settype($json, $builtinType);

        return $json;
    }

    /**
     * Returns the class name of the given object type.
     *
     * @param Type $type
     *
     * @return string|Closure
     *
     * @throws DomainException
     */
    private function getClassName(Type $type)
    {
        $className = $type->getClassName();

        // @codeCoverageIgnoreStart
        if ($className === null) {
            // This should never happen
            throw new DomainException('Type has no valid class name');
        }
        // @codeCoverageIgnoreEnd

        if (array_key_exists($className, $this->classMap)) {
            return $this->classMap[$className];
        }

        return $className;
    }

    /**
     * Cast node to collection.
     *
     * @param mixed $json
     * @param Type  $type
     *
     * @return mixed[]
     *
     * @throws DomainException
     */
    private function asCollection($json, Type $type): array
    {
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
     * @return mixed
     *
     * @throws DomainException
     */
    private function asObject($json, Type $type)
    {
        $className = $this->getClassName($type);

        // Execute closure to get the mapped class name
        if ($className instanceof Closure) {
            $className = $className($json);
        }

        if ($this->isCustomType($className)) {
            return $this->callCustomClosure($json, $className);
        }

        return $this->map($json, $className);
    }

    /**
     * Determine if the specified type is a custom type.
     *
     * @param string $type
     *
     * @return bool
     */
    private function isCustomType(string $type): bool
    {
        return array_key_exists($type, $this->types);
    }

    /**
     * Call the custom closure for the specified type.
     *
     * @param mixed  $json
     * @param string $type
     *
     * @return mixed
     */
    private function callCustomClosure($json, string $type)
    {
        $callback = $this->types[$type];
        return $callback($json);
    }
}
