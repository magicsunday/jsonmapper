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

use function is_string;

/**
 * Represents the configurable defaults for the JsonMapper.
 */
final class JsonMapperConfig
{
    /**
     * Creates a new configuration instance with the provided defaults.
     */
    public function __construct(
        private bool $strictMode = false,
        private bool $ignoreUnknownProperties = false,
        private bool $treatNullAsEmptyCollection = false,
        private string $defaultDateFormat = DateTimeInterface::ATOM,
        private bool $allowScalarToObjectCasting = false,
    ) {
    }

    /**
     * Creates a configuration instance from a serialized array representation.
     *
     * @param array<string, mixed> $data Configuration values indexed by property name
     */
    public static function fromArray(array $data): self
    {
        $defaultDateFormat = $data['defaultDateFormat'] ?? DateTimeInterface::ATOM;

        if (!is_string($defaultDateFormat)) {
            $defaultDateFormat = DateTimeInterface::ATOM;
        }

        return new self(
            (bool) ($data['strictMode'] ?? false),
            (bool) ($data['ignoreUnknownProperties'] ?? false),
            (bool) ($data['treatNullAsEmptyCollection'] ?? false),
            $defaultDateFormat,
            (bool) ($data['allowScalarToObjectCasting'] ?? false),
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
            'ignoreUnknownProperties'    => $this->ignoreUnknownProperties,
            'treatNullAsEmptyCollection' => $this->treatNullAsEmptyCollection,
            'defaultDateFormat'          => $this->defaultDateFormat,
            'allowScalarToObjectCasting' => $this->allowScalarToObjectCasting,
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
     * Indicates whether unknown properties should be ignored during mapping.
     */
    public function shouldIgnoreUnknownProperties(): bool
    {
        return $this->ignoreUnknownProperties;
    }

    /**
     * Indicates whether null collections should be treated as empty collections.
     */
    public function shouldTreatNullAsEmptyCollection(): bool
    {
        return $this->treatNullAsEmptyCollection;
    }

    /**
     * Returns the default date format used by the mapper.
     */
    public function getDefaultDateFormat(): string
    {
        return $this->defaultDateFormat;
    }

    /**
     * Indicates whether scalar values should be cast to objects when possible.
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
