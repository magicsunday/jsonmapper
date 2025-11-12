<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Configuration;

use MagicSunday\JsonMapper\Context\MappingContext;

/**
 * Defines configuration options for mapping operations.
 */
final class MappingConfiguration
{
    public function __construct(
        private bool $strictMode = false,
        private bool $collectErrors = true,
    ) {
    }

    public static function lenient(): self
    {
        return new self(false, true);
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
        );
    }

    public function withErrorCollection(bool $collect): self
    {
        $clone                = clone $this;
        $clone->collectErrors = $collect;

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

    /**
     * @return array<string, bool>
     */
    public function toOptions(): array
    {
        return [
            MappingContext::OPTION_STRICT_MODE    => $this->strictMode,
            MappingContext::OPTION_COLLECT_ERRORS => $this->collectErrors,
        ];
    }
}
