<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Stub;

use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;

/**
 * Counts how often the property list of a class is asked for.
 *
 * Decorates rather than extends: ReflectionExtractor is marked final, and the question here is how
 * often the mapper CONSULTS an extractor - which a decorator answers without changing what the
 * extractor returns, as a mock with a canned list would.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class CountingPropertyListExtractor implements PropertyListExtractorInterface
{
    public int $calls = 0;

    private readonly ReflectionExtractor $inner;

    public function __construct()
    {
        $this->inner = new ReflectionExtractor();
    }

    /**
     * @param string       $class   Class whose properties are requested.
     * @param array<mixed> $context Extraction context passed through unchanged.
     *
     * @return array<string>|null Property names, or null when the class exposes none
     */
    public function getProperties(string $class, array $context = []): ?array
    {
        ++$this->calls;

        return $this->inner->getProperties($class, $context);
    }
}
