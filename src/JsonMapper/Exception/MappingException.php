<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Exception;

use RuntimeException;
use Throwable;

/**
 * Base exception for mapping related failures.
 */
abstract class MappingException extends RuntimeException
{
    /**
     * @param string $message   Human readable description of the failure scenario.
     * @param string $path      JSON pointer or dotted path identifying the failing value.
     * @param int $code         Optional error code to bubble up to the caller.
     * @param Throwable|null $previous Underlying cause, if the exception wraps another failure.
     */
    public function __construct(
        string $message,
        private readonly string $path,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns the JSON path pointing to the value that could not be mapped.
     *
     * Callers can use the path to inform end users about the exact location of the
     * mapping problem or to log structured diagnostics.
     *
     * @return string JSON pointer or dotted path describing the failing location.
     */
    public function getPath(): string
    {
        return $this->path;
    }
}
