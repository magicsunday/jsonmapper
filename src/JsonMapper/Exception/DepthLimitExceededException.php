<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Exception;

use function sprintf;

/**
 * Signals that a payload nests deeper than the configured limit.
 */
final class DepthLimitExceededException extends MappingException
{
    /**
     * @param string $path  Path of the first level beyond the limit.
     * @param int    $limit Nesting depth the mapper accepts.
     */
    public function __construct(string $path, private readonly int $limit)
    {
        parent::__construct(
            sprintf('Nesting depth limit of %d exceeded at %s.', $limit, $path),
            $path,
        );
    }

    /**
     * Returns the nesting depth the mapper accepts.
     *
     * A level is one step of the path, a property or a collection element. A consumer can state
     * the limit to the sender of the payload without parsing the message.
     *
     * @return int Configured limit
     */
    public function getLimit(): int
    {
        return $this->limit;
    }
}
