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
     * @param mixed                $rootInput The original JSON payload handed to the mapper
     * @param array<string, mixed> $options   Context options influencing mapping behaviour
     */
    public function __construct(private readonly mixed $rootInput, array $options = [])
    {
        $this->options = $options;
    }

    /**
     * Returns the root JSON input value.
     *
     * @return mixed Original payload that initiated the current mapping run
     */
    public function getRootInput(): mixed
    {
        return $this->rootInput;
    }

    /**
     * Returns the current path inside the JSON structure.
     *
     * @return string Dot-separated path beginning with the root symbol
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
     * @param string|int           $segment  Segment appended to the path for the callback execution
     * @param callable(self):mixed $callback Callback executed while the segment is in place
     *
     * @return mixed Result produced by the callback
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
     *
     * @param string                $message   Human-readable description of the failure
     * @param MappingException|null $exception Optional exception associated with the failure
     *
     * @return void
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
     *
     * @param MappingException $exception Exception raised during mapping
     *
     * @return void
     */
    public function recordException(MappingException $exception): void
    {
        $this->addError($exception->getMessage(), $exception);
    }

    /**
     * Returns collected mapping errors.
     *
     * @return list<string> Error messages collected so far
     */
    public function getErrors(): array
    {
        return array_map(
            static fn (MappingError $error): string => $error->getMessage(),
            $this->errorRecords,
        );
    }

    /**
     * Indicates whether mapping errors should be collected instead of throwing immediately.
     *
     * @return bool True when error aggregation is enabled
     */
    public function shouldCollectErrors(): bool
    {
        return (bool) ($this->options[self::OPTION_COLLECT_ERRORS] ?? true);
    }

    /**
     * Indicates whether the mapper operates in strict mode.
     *
     * @return bool True when missing or unknown properties result in failures
     */
    public function isStrictMode(): bool
    {
        return (bool) ($this->options[self::OPTION_STRICT_MODE] ?? false);
    }

    /**
     * Indicates whether unknown properties from the input should be ignored.
     *
     * @return bool True when extra input properties are silently skipped
     */
    public function shouldIgnoreUnknownProperties(): bool
    {
        return (bool) ($this->options[self::OPTION_IGNORE_UNKNOWN_PROPERTIES] ?? false);
    }

    /**
     * Indicates whether null collections should be normalised to empty collections.
     *
     * @return bool True when null collections are replaced with empty instances
     */
    public function shouldTreatNullAsEmptyCollection(): bool
    {
        return (bool) ($this->options[self::OPTION_TREAT_NULL_AS_EMPTY_COLLECTION] ?? false);
    }

    /**
     * Returns the default date format used for date conversions.
     *
     * @return string Date format string compatible with {@see DateTimeInterface::format()}
     */
    public function getDefaultDateFormat(): string
    {
        $format = $this->options[self::OPTION_DEFAULT_DATE_FORMAT] ?? DateTimeInterface::ATOM;

        if (!is_string($format) || $format === '') {
            return DateTimeInterface::ATOM;
        }

        return $format;
    }

    /**
     * Indicates whether scalar values are allowed to be coerced into objects when possible.
     *
     * @return bool True when scalar-to-object casting is enabled
     */
    public function shouldAllowScalarToObjectCasting(): bool
    {
        return (bool) ($this->options[self::OPTION_ALLOW_SCALAR_TO_OBJECT_CASTING] ?? false);
    }

    /**
     * Returns all options.
     *
     * @return array<string, mixed> Associative array of context options
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Returns a single option by name.
     *
     * @param string $name    Option name as defined by the {@see self::OPTION_*} constants
     * @param mixed  $default Fallback value returned when the option is not set
     *
     * @return mixed Stored option value or the provided default
     */
    public function getOption(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * Replaces the stored options.
     *
     * @param array<string, mixed> $options Complete set of options to store
     *
     * @return void
     */
    public function replaceOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * Returns collected mapping errors with contextual details.
     *
     * @return list<MappingError> Error records including message, path, and exception
     */
    public function getErrorRecords(): array
    {
        return $this->errorRecords;
    }

    /**
     * Returns the number of collected errors currently stored in the context.
     *
     * @return int Count of collected errors
     */
    public function getErrorCount(): int
    {
        return count($this->errorRecords);
    }

    /**
     * Truncates the stored errors to the given number of entries.
     *
     * @param int $count Maximum number of records to retain
     *
     * @return void
     */
    public function trimErrors(int $count): void
    {
        $this->errorRecords = array_slice($this->errorRecords, 0, $count);
    }
}
