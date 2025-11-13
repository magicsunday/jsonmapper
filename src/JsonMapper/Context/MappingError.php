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
    public function __construct(
        private string $path,
        private string $message,
        private ?MappingException $exception = null,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getException(): ?MappingException
    {
        return $this->exception;
    }
}
