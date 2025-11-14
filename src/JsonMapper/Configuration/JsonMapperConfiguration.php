<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Configuration;

use DateTimeInterface;
use MagicSunday\JsonMapper\Context\MappingContext;

use function is_string;

/**
 * Defines all configurable options available for JsonMapper.
 */
final class JsonMapperConfiguration
{
    /**
     * Creates a new configuration instance with optional overrides.
     *
     * @param bool   $strictMode                   Whether unknown/missing properties should trigger errors
     * @param bool   $collectErrors                Whether encountered mapping errors should be collected
     * @param bool   $emptyStringIsNull            Whether empty strings are converted to null
     * @param bool   $ignoreUnknownProperties      Whether properties missing in the destination type are ignored
     * @param bool   $treatNullAsEmptyCollection   Whether null collections are replaced with empty collections
     * @param string $defaultDateFormat            Default `DateTimeInterface` format used for serialization/deserialization
     * @param bool   $allowScalarToObjectCasting   Whether scalars can be coerced into objects when supported
     */
    public function __construct(
        private bool $strictMode = false,
        private bool $collectErrors = true,
        private bool $emptyStringIsNull = false,
        private bool $ignoreUnknownProperties = false,
        private bool $treatNullAsEmptyCollection = false,
        private string $defaultDateFormat = DateTimeInterface::ATOM,
        private bool $allowScalarToObjectCasting = false,
    ) {
    }

    /**
     * Returns a lenient configuration with default settings.
     *
     * @return self Configuration tuned for permissive mappings
     */
    public static function lenient(): self
    {
        return new self();
    }

    /**
     * Returns a strict configuration that reports unknown and missing properties.
     *
     * @return self Configuration tuned for strict validation
     */
    public static function strict(): self
    {
        return new self(true);
    }

    /**
     * Restores a configuration instance from the provided array.
     *
     * @param array<string, mixed> $data Configuration values indexed by property name
     *
     * @return self Configuration populated with the provided overrides
     */
    public static function fromArray(array $data): self
    {
        $defaultDateFormat = $data['defaultDateFormat'] ?? DateTimeInterface::ATOM;

        if (!is_string($defaultDateFormat) || $defaultDateFormat === '') {
            $defaultDateFormat = DateTimeInterface::ATOM;
        }

        return new self(
            (bool) ($data['strictMode'] ?? false),
            (bool) ($data['collectErrors'] ?? true),
            (bool) ($data['emptyStringIsNull'] ?? false),
            (bool) ($data['ignoreUnknownProperties'] ?? false),
            (bool) ($data['treatNullAsEmptyCollection'] ?? false),
            $defaultDateFormat,
            (bool) ($data['allowScalarToObjectCasting'] ?? false),
        );
    }

    /**
     * Restores a configuration instance based on the provided mapping context.
     *
     * @return self Configuration aligned with the supplied context options
     */
    public static function fromContext(MappingContext $context): self
    {
        return new self(
            $context->isStrictMode(),
            $context->shouldCollectErrors(),
            (bool) $context->getOption(MappingContext::OPTION_TREAT_EMPTY_STRING_AS_NULL, false),
            $context->shouldIgnoreUnknownProperties(),
            $context->shouldTreatNullAsEmptyCollection(),
            $context->getDefaultDateFormat(),
            $context->shouldAllowScalarToObjectCasting(),
        );
    }

    /**
     * Serializes the configuration into an array representation.
     *
     * @return array<string, bool|string> Scalar configuration flags indexed by option name
     */
    public function toArray(): array
    {
        return [
            'strictMode'                 => $this->strictMode,
            'collectErrors'              => $this->collectErrors,
            'emptyStringIsNull'          => $this->emptyStringIsNull,
            'ignoreUnknownProperties'    => $this->ignoreUnknownProperties,
            'treatNullAsEmptyCollection' => $this->treatNullAsEmptyCollection,
            'defaultDateFormat'          => $this->defaultDateFormat,
            'allowScalarToObjectCasting' => $this->allowScalarToObjectCasting,
        ];
    }

    /**
     * Converts the configuration to mapping context options.
     *
     * @return array<string, bool|string> Mapping context option bag compatible with {@see MappingContext}
     */
    public function toOptions(): array
    {
        return [
            MappingContext::OPTION_STRICT_MODE                    => $this->strictMode,
            MappingContext::OPTION_COLLECT_ERRORS                 => $this->collectErrors,
            MappingContext::OPTION_TREAT_EMPTY_STRING_AS_NULL     => $this->emptyStringIsNull,
            MappingContext::OPTION_IGNORE_UNKNOWN_PROPERTIES      => $this->ignoreUnknownProperties,
            MappingContext::OPTION_TREAT_NULL_AS_EMPTY_COLLECTION => $this->treatNullAsEmptyCollection,
            MappingContext::OPTION_DEFAULT_DATE_FORMAT            => $this->defaultDateFormat,
            MappingContext::OPTION_ALLOW_SCALAR_TO_OBJECT_CASTING => $this->allowScalarToObjectCasting,
        ];
    }

    /**
     * Indicates whether strict mode is enabled.
     *
     * @return bool True when unknown or missing properties are treated as failures
     */
    public function isStrictMode(): bool
    {
        return $this->strictMode;
    }

    /**
     * Indicates whether errors should be collected during mapping.
     *
     * @return bool True when mapper should aggregate errors instead of failing fast
     */
    public function shouldCollectErrors(): bool
    {
        return $this->collectErrors;
    }

    /**
     * Indicates whether empty strings should be treated as null values.
     *
     * @return bool True when empty string values are mapped to null
     */
    public function shouldTreatEmptyStringAsNull(): bool
    {
        return $this->emptyStringIsNull;
    }

    /**
     * Indicates whether unknown properties should be ignored.
     *
     * @return bool True when incoming properties without a target counterpart are skipped
     */
    public function shouldIgnoreUnknownProperties(): bool
    {
        return $this->ignoreUnknownProperties;
    }

    /**
     * Indicates whether null collections should be converted to empty collections.
     *
     * @return bool True when null collection values are normalised to empty collections
     */
    public function shouldTreatNullAsEmptyCollection(): bool
    {
        return $this->treatNullAsEmptyCollection;
    }

    /**
     * Returns the default date format used for date conversions.
     *
     * @return string Date format string compatible with {@see DateTimeInterface::format()}
     */
    public function getDefaultDateFormat(): string
    {
        return $this->defaultDateFormat;
    }

    /**
     * Indicates whether scalar values may be cast to objects.
     *
     * @return bool True when scalar-to-object coercion should be attempted
     */
    public function shouldAllowScalarToObjectCasting(): bool
    {
        return $this->allowScalarToObjectCasting;
    }

    /**
     * Returns a copy with the strict mode flag toggled.
     *
     * @param bool $enabled Whether strict mode should be enabled for the clone
     *
     * @return self Cloned configuration reflecting the requested strictness
     */
    public function withStrictMode(bool $enabled): self
    {
        $clone             = clone $this;
        $clone->strictMode = $enabled;

        return $clone;
    }

    /**
     * Returns a copy with the error collection flag toggled.
     *
     * @param bool $collect Whether errors should be aggregated in the clone
     *
     * @return self Cloned configuration applying the collection behaviour
     */
    public function withErrorCollection(bool $collect): self
    {
        $clone                = clone $this;
        $clone->collectErrors = $collect;

        return $clone;
    }

    /**
     * Returns a copy with the empty-string-as-null flag toggled.
     *
     * @param bool $enabled Whether empty strings should become null for the clone
     *
     * @return self Cloned configuration applying the string handling behaviour
     */
    public function withEmptyStringAsNull(bool $enabled): self
    {
        $clone                    = clone $this;
        $clone->emptyStringIsNull = $enabled;

        return $clone;
    }

    /**
     * Returns a copy with the ignore-unknown-properties flag toggled.
     *
     * @param bool $enabled Whether unknown properties should be ignored in the clone
     *
     * @return self Cloned configuration reflecting the requested behaviour
     */
    public function withIgnoreUnknownProperties(bool $enabled): self
    {
        $clone                          = clone $this;
        $clone->ignoreUnknownProperties = $enabled;

        return $clone;
    }

    /**
     * Returns a copy with the treat-null-as-empty-collection flag toggled.
     *
     * @param bool $enabled Whether null collections should be normalised for the clone
     *
     * @return self Cloned configuration applying the collection normalisation behaviour
     */
    public function withTreatNullAsEmptyCollection(bool $enabled): self
    {
        $clone                             = clone $this;
        $clone->treatNullAsEmptyCollection = $enabled;

        return $clone;
    }

    /**
     * Returns a copy with a different default date format.
     *
     * @param string $format Desired default format compatible with {@see DateTimeInterface::format()}
     *
     * @return self Cloned configuration containing the new date format
     */
    public function withDefaultDateFormat(string $format): self
    {
        $clone                    = clone $this;
        $clone->defaultDateFormat = $format;

        return $clone;
    }

    /**
     * Returns a copy with the scalar-to-object casting flag toggled.
     *
     * @param bool $enabled Whether scalar values should be coerced to objects in the clone
     *
     * @return self Cloned configuration defining the scalar coercion behaviour
     */
    public function withScalarToObjectCasting(bool $enabled): self
    {
        $clone                             = clone $this;
        $clone->allowScalarToObjectCasting = $enabled;

        return $clone;
    }
}
