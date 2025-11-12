<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Value;

use LogicException;
use MagicSunday\JsonMapper\Context\MappingContext;
use Symfony\Component\TypeInfo\Type;

use function sprintf;

/**
 * Stores custom conversion handlers.
 */
final class CustomTypeRegistry
{
    /**
     * @var list<TypeHandlerInterface>
     */
    private array $handlers = [];

    /**
     * Registers the converter for the provided class name.
     *
     * @param callable(mixed):mixed|callable(mixed, MappingContext):mixed $converter
     */
    public function register(string $className, callable $converter): void
    {
        $this->registerHandler(new ClosureTypeHandler($className, $converter));
    }

    /**
     * Registers a custom type handler.
     */
    public function registerHandler(TypeHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * Returns TRUE if a handler for the type exists.
     */
    public function supports(Type $type, mixed $value): bool
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($type, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Executes the converter for the class.
     */
    public function convert(Type $type, mixed $value, MappingContext $context): mixed
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($type, $value)) {
                return $handler->convert($type, $value, $context);
            }
        }

        throw new LogicException(sprintf('No custom type handler registered for %s.', $type::class));
    }
}
