<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Value;

use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Value\CustomTypeRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class CustomTypeRegistryTest extends TestCase
{
    #[Test]
    public function itNormalizesSingleArgumentClosures(): void
    {
        $registry = new CustomTypeRegistry();
        $registry->register('Foo', static fn (mixed $value): array => (array) $value);

        $context = new MappingContext([]);

        self::assertTrue($registry->has('Foo'));
        self::assertSame(['bar' => 'baz'], $registry->convert('Foo', ['bar' => 'baz'], $context));
    }

    #[Test]
    public function itPassesContextToConverters(): void
    {
        $registry = new CustomTypeRegistry();
        $registry->register('Foo', static function (mixed $value, MappingContext $context): array {
            $context->addError('called');

            return (array) $value;
        });

        $context = new MappingContext([]);
        $registry->convert('Foo', ['payload'], $context);

        self::assertSame(['called'], $context->getErrors());
    }
}
