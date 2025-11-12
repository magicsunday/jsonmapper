<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Context;

/**
 * Represents the state shared while mapping JSON structures.
 */
final class MappingContext
{
    /**
     * @var list<string>
     */
    private array $pathSegments;

    /**
     * @var list<string>
     */
    private array $errors = [];

    /**
     * @param mixed                $rootInput The original JSON payload
     * @param array<string, mixed> $options   Context options
     */
    public function __construct(
        private readonly mixed $rootInput,
        private readonly array $options = [],
    ) {
        $this->pathSegments = [];
    }

    /**
     * Returns the root JSON input value.
     */
    public function getRootInput(): mixed
    {
        return $this->rootInput;
    }

    /**
     * Returns the current path inside the JSON structure.
     */
    public function getPath(): string
    {
        if ($this->pathSegments === []) {
            return '$';
        }

        return '$.' . implode('.', $this->pathSegments);
    }

    /**
     * Executes the callback while appending the provided segment to the path.
     *
     * @param callable(self):mixed $callback
     */
    public function withPathSegment(string|int $segment, callable $callback): mixed
    {
        $this->pathSegments[] = (string) $segment;

        try {
            return $callback($this);
        } finally {
            array_pop($this->pathSegments);
        }
    }

    /**
     * Stores the error message for later consumption.
     */
    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    /**
     * Returns collected mapping errors.
     *
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Returns all options.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Returns a single option by name.
     */
    public function getOption(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }
}
