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
     */
    public static function lenient(): self
    {
        return new self();
    }

    /**
     * Returns a strict configuration that reports unknown and missing properties.
     */
    public static function strict(): self
    {
        return new self(true);
    }

    /**
     * Restores a configuration instance from the provided array.
     *
     * @param array<string, mixed> $data Configuration values indexed by property name
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
     * @return array<string, bool|string>
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
     * @return array<string, bool|string>
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
     */
    public function isStrictMode(): bool
    {
        return $this->strictMode;
    }

    /**
     * Indicates whether errors should be collected during mapping.
     */
    public function shouldCollectErrors(): bool
    {
        return $this->collectErrors;
    }

    /**
     * Indicates whether empty strings should be treated as null values.
     */
    public function shouldTreatEmptyStringAsNull(): bool
    {
        return $this->emptyStringIsNull;
    }

    /**
     * Indicates whether unknown properties should be ignored.
     */
    public function shouldIgnoreUnknownProperties(): bool
    {
        return $this->ignoreUnknownProperties;
    }

    /**
     * Indicates whether null collections should be converted to empty collections.
     */
    public function shouldTreatNullAsEmptyCollection(): bool
    {
        return $this->treatNullAsEmptyCollection;
    }

    /**
     * Returns the default date format used for date conversions.
     */
    public function getDefaultDateFormat(): string
    {
        return $this->defaultDateFormat;
    }

    /**
     * Indicates whether scalar values may be cast to objects.
     */
    public function shouldAllowScalarToObjectCasting(): bool
    {
        return $this->allowScalarToObjectCasting;
    }

    /**
     * Returns a copy with the strict mode flag toggled.
     */
    public function withStrictMode(bool $enabled): self
    {
        $clone             = clone $this;
        $clone->strictMode = $enabled;

        return $clone;
    }

    /**
     * Returns a copy with the error collection flag toggled.
     */
    public function withErrorCollection(bool $collect): self
    {
        $clone                = clone $this;
        $clone->collectErrors = $collect;

        return $clone;
    }

    /**
     * Returns a copy with the empty-string-as-null flag toggled.
     */
    public function withEmptyStringAsNull(bool $enabled): self
    {
        $clone                    = clone $this;
        $clone->emptyStringIsNull = $enabled;

        return $clone;
    }

    /**
     * Returns a copy with the ignore-unknown-properties flag toggled.
     */
    public function withIgnoreUnknownProperties(bool $enabled): self
    {
        $clone                          = clone $this;
        $clone->ignoreUnknownProperties = $enabled;

        return $clone;
    }

    /**
     * Returns a copy with the treat-null-as-empty-collection flag toggled.
     */
    public function withTreatNullAsEmptyCollection(bool $enabled): self
    {
        $clone                             = clone $this;
        $clone->treatNullAsEmptyCollection = $enabled;

        return $clone;
    }

    /**
     * Returns a copy with a different default date format.
     */
    public function withDefaultDateFormat(string $format): self
    {
        $clone                    = clone $this;
        $clone->defaultDateFormat = $format;

        return $clone;
    }

    /**
     * Returns a copy with the scalar-to-object casting flag toggled.
     */
    public function withScalarToObjectCasting(bool $enabled): self
    {
        $clone                             = clone $this;
        $clone->allowScalarToObjectCasting = $enabled;

        return $clone;
    }
}
