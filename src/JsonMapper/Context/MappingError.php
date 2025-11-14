<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Context;

use MagicSunday\JsonMapper\Exception\MappingException;

/**
 * Represents a collected mapping error.
 */
final readonly class MappingError
{
    /**
     * @param string                 $path       JSON path pointing to the failing property
     * @param string                 $message    Human-readable description of the failure
     * @param MappingException|null  $exception  Exception that triggered the error, when available
     */
    public function __construct(
        private string $path,
        private string $message,
        private ?MappingException $exception = null,
    ) {
    }

    /**
     * Returns the JSON path that triggered the error.
     *
     * @return string Path formatted using dot notation starting at the root symbol
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Returns the descriptive error message.
     *
     * @return string Human-readable explanation of the failure
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Returns the exception instance associated with the error, when one was recorded.
     *
     * @return MappingException|null Underlying exception or null when only a message was recorded
     */
    public function getException(): ?MappingException
    {
        return $this->exception;
    }
}
