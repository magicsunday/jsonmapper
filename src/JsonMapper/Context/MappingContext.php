<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Context;

use DateTimeInterface;
use MagicSunday\JsonMapper\Exception\MappingException;

use function array_slice;
use function count;
use function implode;
use function is_string;

/**
 * Represents the state shared while mapping JSON structures.
 */
final class MappingContext
{
    public const string OPTION_STRICT_MODE = 'strict_mode';

    public const string OPTION_COLLECT_ERRORS = 'collect_errors';

    public const string OPTION_TREAT_EMPTY_STRING_AS_NULL = 'empty_string_is_null';

    public const string OPTION_IGNORE_UNKNOWN_PROPERTIES = 'ignore_unknown_properties';

    public const string OPTION_TREAT_NULL_AS_EMPTY_COLLECTION = 'treat_null_as_empty_collection';

    public const string OPTION_DEFAULT_DATE_FORMAT = 'default_date_format';

    public const string OPTION_ALLOW_SCALAR_TO_OBJECT_CASTING = 'allow_scalar_to_object_casting';

    /**
     * @var list<string>
     */
    private array $pathSegments = [];

    /**
     * @var list<MappingError>
     */
    private array $errorRecords = [];

    /**
     * @var array<string, mixed>
     */
    private array $options;

    /**
     * @param mixed                $rootInput The original JSON payload
     * @param array<string, mixed> $options   Context options
     */
    public function __construct(private readonly mixed $rootInput, array $options = [])
    {
        $this->options = $options;
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
    public function addError(string $message, ?MappingException $exception = null): void
    {
        if (!$this->shouldCollectErrors()) {
            return;
        }

        $this->errorRecords[] = new MappingError($this->getPath(), $message, $exception);
    }

    /**
     * Stores the exception and message for later consumption.
     */
    public function recordException(MappingException $exception): void
    {
        $this->addError($exception->getMessage(), $exception);
    }

    /**
     * Returns collected mapping errors.
     *
     * @return list<string>
     */
    public function getErrors(): array
    {
        return array_map(
            static fn (MappingError $error): string => $error->getMessage(),
            $this->errorRecords,
        );
    }

    public function shouldCollectErrors(): bool
    {
        return (bool) ($this->options[self::OPTION_COLLECT_ERRORS] ?? true);
    }

    public function isStrictMode(): bool
    {
        return (bool) ($this->options[self::OPTION_STRICT_MODE] ?? false);
    }

    public function shouldIgnoreUnknownProperties(): bool
    {
        return (bool) ($this->options[self::OPTION_IGNORE_UNKNOWN_PROPERTIES] ?? false);
    }

    public function shouldTreatNullAsEmptyCollection(): bool
    {
        return (bool) ($this->options[self::OPTION_TREAT_NULL_AS_EMPTY_COLLECTION] ?? false);
    }

    public function getDefaultDateFormat(): string
    {
        $format = $this->options[self::OPTION_DEFAULT_DATE_FORMAT] ?? DateTimeInterface::ATOM;

        if (!is_string($format) || $format === '') {
            return DateTimeInterface::ATOM;
        }

        return $format;
    }

    public function shouldAllowScalarToObjectCasting(): bool
    {
        return (bool) ($this->options[self::OPTION_ALLOW_SCALAR_TO_OBJECT_CASTING] ?? false);
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

    /**
     * Replaces the stored options.
     *
     * @param array<string, mixed> $options
     */
    public function replaceOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * Returns collected mapping errors with contextual details.
     *
     * @return list<MappingError>
     */
    public function getErrorRecords(): array
    {
        return $this->errorRecords;
    }

    public function getErrorCount(): int
    {
        return count($this->errorRecords);
    }

    public function trimErrors(int $count): void
    {
        $this->errorRecords = array_slice($this->errorRecords, 0, $count);
    }
}
