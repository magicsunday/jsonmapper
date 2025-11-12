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
    public function __construct(
        string $message,
        private readonly string $path,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
