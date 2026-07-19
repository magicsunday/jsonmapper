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

use function array_key_exists;
use function array_replace;
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
     * Timezone applied when a date format carries none of its own.
     *
     * Without it, parsing such a format falls back to the process default, so the same payload
     * decodes to a different instant on every differently configured host. PHP ignores the
     * timezone when the format does supply one, so this never overrides what a payload states.
     */
    public const string OPTION_DEFAULT_TIMEZONE = 'default_timezone';

    /**
     * Whether a mapping failure aborts the run.
     *
     * Not part of JsonMapperConfiguration: it is not a mapping preference but the difference
     * between the two entry points. map() raises on the first failure in strict mode; the whole
     * purpose of mapWithReport() is to hand back a report, so it collects instead. Strict mode
     * still decides WHAT counts as a failure either way.
     */
    public const string OPTION_ABORT_ON_ERROR = 'abort_on_error';

    /**
     * Timezone assumed for a zoneless date format. UTC rather than the host default, so that the
     * same payload yields the same instant wherever it is mapped.
     */
    private const string DEFAULT_TIMEZONE = 'UTC';

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
     * Executes the callback with error collection switched on, restoring the previous setting
     * afterwards.
     *
     * Some decisions are made by observing whether a conversion produced an error - union
     * candidate selection being the one that needs it. That observation must not depend on the
     * caller's reporting preference: with collection switched off nothing is recorded, so the
     * observation would always report success and the decision would silently change. Records
     * written during the callback are the caller's to keep or discard via {@see trimErrors()}.
     *
     * @template TReturn
     *
     * @param callable(self): TReturn $callback Callback executed while collection is forced on
     *
     * @return TReturn Result produced by the callback
     */
    public function withForcedErrorCollection(callable $callback): mixed
    {
        return $this->withOverriddenOptions(
            [
                self::OPTION_COLLECT_ERRORS => true,

                // Strict mode is overridden for the same reason collection is forced on: a
                // candidate trial is an internal question, not a mapping the caller asked for.
                // Left on, the first candidate that fails aborts the whole run - so a union whose
                // LATER member matches perfectly still raised, naming the type that happened to be
                // tried first. A genuine failure is still raised afterwards, by the caller, once
                // every candidate has actually been tried.
                self::OPTION_STRICT_MODE => false,
            ],
            $callback,
        );
    }

    /**
     * Executes the callback with the given options in force, restoring the previous values
     * afterwards.
     *
     * @template TReturn
     *
     * @param array<string, mixed>    $overrides Option values to apply for the duration
     * @param callable(self): TReturn $callback  Callback executed while the overrides are in place
     *
     * @return TReturn Result produced by the callback
     */
    private function withOverriddenOptions(array $overrides, callable $callback): mixed
    {
        // array_key_exists() rather than ??: a stored null and an absent key read the same through
        // the option accessors, which coalesce both to a default. They differ only in the raw bag
        // returned by getOptions(), so restoring "absent" as "null" would hand a caller comparing
        // that bag a difference that was never there.
        $previous = [];

        foreach ($overrides as $name => $value) {
            $previous[$name] = array_key_exists($name, $this->options)
                ? [true, $this->options[$name]]
                : [false, null];

            $this->options[$name] = $value;
        }

        try {
            return $callback($this);
        } finally {
            foreach ($previous as $name => [$wasSet, $value]) {
                if ($wasSet) {
                    $this->options[$name] = $value;
                } else {
                    unset($this->options[$name]);
                }
            }
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
     * Raises the failure when the run aborts on the first one, and records it otherwise.
     *
     * The name states the order because the order is the whole contract: a site that must record
     * even while aborting cannot use this, and two do. Each explains itself at its own call site.
     *
     * @param MappingException $exception Failure to raise or record
     *
     * @return void
     *
     * @throws MappingException When the entry point aborts on the first failure
     */
    public function throwOrRecord(MappingException $exception): void
    {
        // When the run aborts, the exception reaches a catch site that records it, so recording
        // here as well files the same failure twice - visible to a caller that supplies its own
        // context and inspects it after catching.
        if ($this->shouldAbortOnError()) {
            throw $exception;
        }

        $this->recordException($exception);
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
     * Indicates whether a mapping failure should abort the run rather than be collected.
     *
     * @return bool True when the first failure raises
     */
    public function shouldAbortOnError(): bool
    {
        return (bool) ($this->options[self::OPTION_ABORT_ON_ERROR] ?? $this->isStrictMode());
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
     * Returns the timezone applied to date formats that carry none of their own.
     *
     * @return string Timezone identifier accepted by {@see \DateTimeZone}
     */
    public function getDefaultTimezone(): string
    {
        $timezone = $this->options[self::OPTION_DEFAULT_TIMEZONE] ?? self::DEFAULT_TIMEZONE;

        if (!is_string($timezone) || $timezone === '') {
            return self::DEFAULT_TIMEZONE;
        }

        return $timezone;
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
        // Merged rather than assigned. The options bag is an extension point: a type handler may
        // put its own keys there, and toOptions() only knows the mapper's own. Replacing the bag
        // wholesale therefore wiped every custom key the moment a nested object rebuilt the
        // configuration from the context - the caller's key was gone from the first nested object
        // onward, silently.
        $this->options = array_replace($this->options, $options);
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
