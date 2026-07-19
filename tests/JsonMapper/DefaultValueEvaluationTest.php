<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\Test\Classes\SharedDefaultHolder;
use MagicSunday\Test\Classes\ThrowingDefaultHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * A declared default may be an expression - a promoted `new X()` initializer - and reflection
 * EVALUATES it when asked for the value. So when the default is fetched decides two things: whether
 * an expression runs at all, and whether its result is shared.
 *
 * Caching the resolved VALUE per class gets both wrong: an unused default runs anyway, and every
 * element of a collection receives the same object.
 *
 * @internal
 */
final class DefaultValueEvaluationTest extends TestCase
{
    #[Test]
    public function itDoesNotEvaluateADefaultThePayloadSupplies(): void
    {
        // The payload supplies the property, so its default is never needed. Evaluating it anyway
        // runs whatever the initializer does - here a throw, in real code a logger, a clock, a
        // container lookup - and the exception is a native one raised from user code, which
        // escapes the mapper's error collection entirely.
        $result = $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"name": "hi", "boom": null}'),
            ThrowingDefaultHolder::class,
        );

        self::assertInstanceOf(ThrowingDefaultHolder::class, $result);
        self::assertSame('hi', $result->name);
    }

    #[Test]
    public function itGivesEachElementItsOwnDefaultInstance(): void
    {
        // A default that constructs an object must construct one PER USE. Resolved once and
        // memoised, every element of a collection is handed the same instance, so mutating it on
        // one mutates it on all - silent shared state between objects that never met.
        $result = $this->getJsonMapper()->map(
            $this->getJsonAsObject('[{"bag": null}, {"bag": null}]'),
            SharedDefaultHolder::class,
        );

        self::assertIsArray($result);
        self::assertCount(2, $result);

        $first  = $result[0];
        $second = $result[1];

        self::assertInstanceOf(SharedDefaultHolder::class, $first);
        self::assertInstanceOf(SharedDefaultHolder::class, $second);
        self::assertNotSame($first->bag, $second->bag, 'Each element gets its own default object.');
    }
}
