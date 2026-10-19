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
use MagicSunday\JsonMapper\Exception\DepthLimitExceededException;
use MagicSunday\JsonMapper\Exception\MappingException;
use MagicSunday\JsonMapper\Exception\MissingConstructorArgumentException;
use MagicSunday\JsonMapper\Exception\MissingPropertyException;

use function array_key_exists;
use function array_key_last;
use function array_pop;
use function array_replace;
use function count;
use function filter_var;
use function implode;
use function is_int;
use function is_string;
use function max;
use function min;
use function str_starts_with;

use const FILTER_VALIDATE_INT;
use const PHP_INT_MAX;

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
     * How many failures the context keeps.
     *
     * Without a ceiling the number of records, and with it the memory a report holds, would be
     * decided by the payload: a body of many unknown keys files one record per key. What is dropped
     * is counted, so a report can say that it is incomplete.
     */
    public const string OPTION_MAX_ERRORS = 'max_errors';

    /**
     * Number of failures kept when the option names none.
     */
    public const int DEFAULT_MAX_ERRORS = 1000;

    /**
     * How deep a payload may nest.
     *
     * A failure builds an exception whose stack trace is as deep as the level it was raised at, and
     * a kept record holds that exception, so a chain of failing levels costs memory that grows with
     * the square of the depth. The limit is what the payload cannot decide: a level beyond it is
     * refused with a failure of its own instead of being mapped.
     */
    public const string OPTION_MAX_DEPTH = 'max_depth';

    /**
     * Nesting depth accepted when the option names none.
     */
    public const int DEFAULT_MAX_DEPTH = 64;

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
     * Number of failures that were not kept because the ceiling had been reached.
     */
    private int $droppedErrors = 0;

    /**
     * Number of records an open trial window may hold, null outside one.
     */
    private ?int $trialCeiling = null;

    /**
     * Number of failures a trial window refused to store because it was full.
     */
    private int $trialRefused = 0;

    /**
     * Number of those refusals that were something other than a missing property.
     */
    private int $trialRefusedFailures = 0;

    /**
     * @param mixed                $rootInput The original JSON payload handed to the mapper
     * @param array<string, mixed> $options   Context options influencing mapping behaviour
     */
    public function __construct(private readonly mixed $rootInput, private array $options = [])
    {
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
     *
     * @throws DepthLimitExceededException When the segment makes the path deeper than the limit
     */
    public function withPathSegment(string|int $segment, callable $callback): mixed
    {
        $this->pathSegments[] = (string) $segment;

        try {
            if (count($this->pathSegments) > $this->getMaxDepth()) {
                throw new DepthLimitExceededException($this->getPath(), $this->getMaxDepth());
            }

            return $callback($this);
        } finally {
            array_pop($this->pathSegments);
        }
    }

    /**
     * Executes the callback with error collection switched on and aborting switched off,
     * restoring the previous settings afterwards.
     *
     * Some decisions are made by observing whether a conversion produced an error - union
     * candidate selection being the one that needs it. That observation must not depend on the
     * caller's reporting preference: with collection switched off nothing is recorded, so the
     * observation would always report success and the decision would silently change. Aborting
     * is switched off so that a failing candidate is recorded and judged instead of ending the
     * run. Records written during the callback are the caller's to keep, raise or discard via
     * {@see trimErrors()}.
     *
     * @template TReturn
     *
     * @param callable(self): TReturn $callback Callback executed while collection is forced on
     *
     * @return TReturn Result produced by the callback
     */
    public function withForcedErrorCollection(callable $callback): mixed
    {
        // A window may hold the ceiling above where it began. The decision a trial serves reads
        // whether a candidate added records, so a ceiling that stopped appending at the records
        // already kept would make a failing candidate look clean, and no ceiling at all would let the
        // payload decide how much a rejected candidate holds before it is trimmed. A window inside
        // another shares the outer allowance, so a payload nesting unions cannot stack one allowance
        // per level, but it always has room for the one record its own decision needs. The records
        // written here are the caller's to trim when the window closes.
        $outerCeiling = $this->trialCeiling;
        $baseline     = count($this->errorRecords);
        // Summed without leaving the integers: a ceiling of PHP_INT_MAX is a legitimate way to say
        // "unbounded", and adding it to a non-zero baseline would turn the allowance into a float.
        $allowance          = $baseline + min($this->getMaxErrors(), PHP_INT_MAX - $baseline);
        $this->trialCeiling = max($outerCeiling ?? $allowance, $baseline + 1);

        try {
            return $this->withOverriddenOptions(
                [
                    self::OPTION_COLLECT_ERRORS => true,

                    // Aborting is overridden for the same reason collection is forced on: a
                    // candidate trial is an internal question, not a mapping the caller asked for.
                    // Left on, the first candidate that fails aborts the whole run - so a union
                    // whose LATER member matches perfectly still raised, naming the type that
                    // happened to be tried first. A genuine failure is still raised afterwards, by
                    // the caller, once every candidate has actually been tried.
                    //
                    // Strict mode itself is NOT overridden. It decides what counts as a failure,
                    // and a value that gets through the trial is still a value the caller asked to
                    // have validated, so the subtree below a union is checked like any other.
                    self::OPTION_ABORT_ON_ERROR => false,
                ],
                $callback,
            );
        } finally {
            $this->trialCeiling = $outerCeiling;
        }
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

        if (count($this->errorRecords) >= ($this->trialCeiling ?? $this->getMaxErrors())) {
            // A refusal inside a trial is not a loss yet. The candidate it belongs to is either
            // rejected, which takes the refusal back, or accepted, which settles it, see
            // settleTrialRecords(). It is counted because a candidate is judged on what it
            // recorded, and a failure that was refused must still be able to reject it.
            if ($this->trialCeiling === null) {
                ++$this->droppedErrors;
            } else {
                ++$this->trialRefused;

                if (!$exception instanceof MissingPropertyException) {
                    ++$this->trialRefusedFailures;
                }
            }

            return;
        }

        $this->errorRecords[] = new MappingError($this->getPath(), $message, $exception);
    }

    /**
     * Stores the exception and message for later consumption.
     *
     * The failure the latest record was made from is not stored a second time. An aborting run
     * raises the failure of the innermost value and every enclosing level catches it on the way
     * out, so a rethrown failure stays a single record at the path of the rejected value however
     * deep it sat. A different failure, also at the same path, is stored.
     *
     * @param MappingException $exception Exception raised during mapping
     *
     * @return void
     */
    public function recordException(MappingException $exception): void
    {
        if (
            ($this->errorRecords !== [])
            && ($this->errorRecords[array_key_last($this->errorRecords)]->getException() === $exception)
        ) {
            return;
        }

        if ($this->repeatsRecordedOmission($exception)) {
            return;
        }

        $this->addError($exception->getMessage(), $exception);
    }

    /**
     * Reports whether a failed construction only repeats an omission that is already recorded.
     *
     * A required constructor argument is a required property too, so strict validation reports the
     * omission first. Construction then fails for the very same absent value, and filing that as
     * well would count one defect twice, and only for the classes that happen to take their values
     * through a constructor. The caller already learns the object was not built from the value. The
     * record has to be one that is still stored: a union candidate drops what it recorded when it
     * is rejected, and the failure of the member is then the only record there is.
     *
     * @param MappingException $exception Failure about to be recorded
     *
     * @return bool True when the missing property of that argument is among the latest records
     */
    private function repeatsRecordedOmission(MappingException $exception): bool
    {
        if (!$exception instanceof MissingConstructorArgumentException) {
            return false;
        }

        $objectPath   = $exception->getPath();
        $expectedPath = $objectPath . '.' . $exception->getArgumentName();

        // The records of the object being built are the latest ones, descendants included, so the
        // walk ends at the first record that belongs to something else. That keeps the cost to the
        // records of one object however many failed before it. A failure filed while the
        // arguments are collected, such as a refused value, can sit between the omissions and the
        // failed construction, which is why the walk cannot stop at the first other kind of record.
        for ($index = count($this->errorRecords) - 1; $index >= 0; --$index) {
            $recorded     = $this->errorRecords[$index]->getException();
            $recordedPath = $this->errorRecords[$index]->getPath();

            if (($recordedPath !== $objectPath) && !str_starts_with($recordedPath, $objectPath . '.')) {
                return false;
            }

            if (
                ($recorded instanceof MissingPropertyException)
                && ($recorded->getPath() === $expectedPath)
            ) {
                return true;
            }
        }

        return false;
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
     * Returns how many failures the context keeps at most.
     *
     * @return int Ceiling for the collected failures, never below one
     */
    public function getMaxErrors(): int
    {
        return self::normaliseMaxErrors($this->options[self::OPTION_MAX_ERRORS] ?? null);
    }

    /**
     * Turns a configured ceiling into a usable one.
     *
     * Settings arrive as arrays from files and the environment, where a number is often a string.
     * An integer, or a string of decimal digits without leading zeros that may carry a sign and
     * surrounding whitespace, of at least one is taken as it is. Anything else, a float, a boolean
     * and a number too large for an integer included, falls back to the default rather than to zero,
     * which would keep nothing.
     *
     * The parameter is untyped on purpose: the value comes from a configuration array or an option
     * bag, whose entries have no type until they are looked at. Public only so that
     * {@see \MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration} can restore through the same
     * rule, it is not part of the surface to build on.
     *
     * @internal
     *
     * @param mixed $value Value read from a configuration array or an option bag
     *
     * @return int Ceiling of at least one
     */
    public static function normaliseMaxErrors(mixed $value): int
    {
        return self::normalisePositiveInteger($value, self::DEFAULT_MAX_ERRORS);
    }

    /**
     * Reads a setting that has to be a whole number of at least one.
     *
     * @param mixed $value   Value read from a configuration array or an option bag
     * @param int   $default Value used when the setting is unusable
     *
     * @return int The number, or the default
     */
    private static function normalisePositiveInteger(mixed $value, int $default): int
    {
        if (!is_int($value) && !is_string($value)) {
            return $default;
        }

        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return ($number === false) ? $default : $number;
    }

    /**
     * Returns the latest depth failure, provided one happened after the given count was read.
     *
     * @param int $countBefore Value of {@see getDepthFailureCount()} read before the work in question
     *
     * @return DepthLimitExceededException|null The failure, or NULL when no level was refused since
     */
    public function getDepthFailureSince(int $countBefore): ?DepthLimitExceededException
    {
        return ($this->depthFailureCount > $countBefore) ? $this->lastDepthFailure : null;
    }

    /**
     * Returns how many levels were refused for being deeper than the limit so far.
     *
     * @return int Number of refusals, which only ever grows during a run
     */
    public function getDepthFailureCount(): int
    {
        return $this->depthFailureCount;
    }

    /**
     * Returns how deep a payload may nest.
     *
     * @return int Number of path levels accepted, never below one
     */
    public function getMaxDepth(): int
    {
        return self::normaliseMaxDepth($this->options[self::OPTION_MAX_DEPTH] ?? null);
    }

    /**
     * Turns a configured depth limit into a usable one.
     *
     * Read by the same rule as {@see normaliseMaxErrors()}: an integer, or a string of decimal
     * digits, of at least one is taken as it is, anything else falls back to the default rather
     * than to zero, which would refuse every level.
     *
     * @internal
     *
     * @param mixed $value Value read from a configuration array or an option bag
     *
     * @return int Limit of at least one
     */
    public static function normaliseMaxDepth(mixed $value): int
    {
        return self::normalisePositiveInteger($value, self::DEFAULT_MAX_DEPTH);
    }

    /**
     * Returns how many failures were not kept because the ceiling had been reached.
     *
     * @return int Number of dropped failures, zero for a complete record
     */
    public function getDroppedErrorCount(): int
    {
        return $this->droppedErrors;
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
     * Returns how many failures trial windows refused to store so far.
     *
     * @internal
     *
     * @return int Running count, to be compared with an earlier reading
     */
    public function getTrialRefusedCount(): int
    {
        return $this->trialRefused;
    }

    /**
     * Returns how many of the refused failures were not a missing property.
     *
     * @internal
     *
     * @return int Running count, to be compared with an earlier reading
     */
    public function getTrialRefusedFailureCount(): int
    {
        return $this->trialRefusedFailures;
    }

    /**
     * Takes back the refusals counted since the given readings, for a candidate that was rejected.
     *
     * @internal
     *
     * @param int $refused  Reading of {@see getTrialRefusedCount()} taken before the candidate
     * @param int $failures Reading of {@see getTrialRefusedFailureCount()} taken before the candidate
     *
     * @return void
     */
    public function restoreTrialRefusals(int $refused, int $failures): void
    {
        $this->trialRefused         = $refused;
        $this->trialRefusedFailures = $failures;
    }

    /**
     * Fits the records an accepted candidate left behind into the ceiling and counts what did not fit.
     *
     * A trial window may hold more than the ceiling allows, and it refuses what is beyond the
     * window. Both are failures the report could not keep, so they are dropped when the outermost
     * window closes. A window inside another leaves them to it. The outer candidate is still being
     * judged on what is there.
     *
     * @internal
     *
     * @param int $refusedBefore Reading of {@see getTrialRefusedCount()} taken before the candidates
     *
     * @return void
     */
    public function settleTrialRecords(int $refusedBefore): void
    {
        if ($this->trialCeiling !== null) {
            return;
        }

        $ceiling  = $this->getMaxErrors();
        $overflow = max(count($this->errorRecords) - $ceiling, 0);

        if ($overflow > 0) {
            $this->trimErrors($ceiling);
        }

        $this->droppedErrors += $overflow + ($this->trialRefused - $refusedBefore);
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
        // Pop the surplus off the end. Copying the retained prefix, or rebuilding the list around
        // the cut, would make a run that keeps rejecting union members pay for the length of the
        // whole report on every trim, while popping costs what is removed.
        while (count($this->errorRecords) > max(0, $count)) {
            array_pop($this->errorRecords);
        }
    }
}
