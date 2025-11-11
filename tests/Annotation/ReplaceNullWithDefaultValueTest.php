<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Annotation;

use MagicSunday\Test\Classes\Initialized;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Class ReplaceNullWithDefaultValueTest.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
class ReplaceNullWithDefaultValueTest extends TestCase
{
    /**
     * Tests that null values are replaced with default values when using the annotation.
     */
    #[Test]
    public function replaceNullWithDefaultValue(): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray(
                    <<<JSON
{
    "integer": null,
    "bool": null,
    "array": null
}
JSON
                ),
                Initialized::class
            );

        self::assertInstanceOf(Initialized::class, $result);
        self::assertSame(10, $result->integer);
        self::assertSame([], $result->array);
        self::assertFalse($result->bool);
    }

    /**
     * Tests that actual values are not replaced even when annotation is present.
     */
    #[Test]
    public function doesNotReplaceActualValues(): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray(
                    <<<JSON
{
    "integer": 42,
    "bool": true,
    "array": ["value1", "value2"]
}
JSON
                ),
                Initialized::class
            );

        self::assertInstanceOf(Initialized::class, $result);
        self::assertSame(42, $result->integer);
        self::assertTrue($result->bool);
        self::assertSame(['value1', 'value2'], $result->array);
    }

    /**
     * Tests that missing properties use default values without the annotation being triggered.
     */
    #[Test]
    public function missingPropertiesUseDefaults(): void
    {
        $result = $this->getJsonMapper()
            ->map(
                $this->getJsonAsArray('{}'),
                Initialized::class
            );

        self::assertInstanceOf(Initialized::class, $result);
        self::assertSame(10, $result->integer);
        self::assertSame([], $result->array);
        self::assertFalse($result->bool);
    }
}
