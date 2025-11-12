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

/**
 * Defines configuration options for mapping operations.
 */
final class MappingConfiguration
{
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

    public static function lenient(): self
    {
        return new self();
    }

    public static function strict(): self
    {
        return new self(true, true);
    }

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

    public function withErrorCollection(bool $collect): self
    {
        $clone                = clone $this;
        $clone->collectErrors = $collect;

        return $clone;
    }

    public function withEmptyStringAsNull(bool $enabled): self
    {
        $clone                    = clone $this;
        $clone->emptyStringIsNull = $enabled;

        return $clone;
    }

    public function withIgnoreUnknownProperties(bool $enabled): self
    {
        $clone                          = clone $this;
        $clone->ignoreUnknownProperties = $enabled;

        return $clone;
    }

    public function withTreatNullAsEmptyCollection(bool $enabled): self
    {
        $clone                             = clone $this;
        $clone->treatNullAsEmptyCollection = $enabled;

        return $clone;
    }

    public function withDefaultDateFormat(string $format): self
    {
        $clone                    = clone $this;
        $clone->defaultDateFormat = $format;

        return $clone;
    }

    public function withScalarToObjectCasting(bool $enabled): self
    {
        $clone                             = clone $this;
        $clone->allowScalarToObjectCasting = $enabled;

        return $clone;
    }

    public function isStrictMode(): bool
    {
        return $this->strictMode;
    }

    public function shouldCollectErrors(): bool
    {
        return $this->collectErrors;
    }

    public function shouldTreatEmptyStringAsNull(): bool
    {
        return $this->emptyStringIsNull;
    }

    public function shouldIgnoreUnknownProperties(): bool
    {
        return $this->ignoreUnknownProperties;
    }

    public function shouldTreatNullAsEmptyCollection(): bool
    {
        return $this->treatNullAsEmptyCollection;
    }

    public function getDefaultDateFormat(): string
    {
        return $this->defaultDateFormat;
    }

    public function shouldAllowScalarToObjectCasting(): bool
    {
        return $this->allowScalarToObjectCasting;
    }

    /**
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
}
