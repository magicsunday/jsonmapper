<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Resolver;

use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Resolver\ClassResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class ClassResolverTest extends TestCase
{
    #[Test]
    public function itResolvesMappedClassNames(): void
    {
        $resolver = new ClassResolver(['BaseClass' => 'MappedClass']);
        $context  = new MappingContext([]);

        self::assertSame('MappedClass', $resolver->resolve('BaseClass', ['json'], $context));
    }

    #[Test]
    public function itSupportsClosuresWithSingleArgument(): void
    {
        $resolver = new ClassResolver(['BaseClass' => static fn (): string => 'FromClosure']);
        $context  = new MappingContext([]);

        self::assertSame('FromClosure', $resolver->resolve('BaseClass', ['json'], $context));
    }

    #[Test]
    public function itSupportsClosuresReceivingContext(): void
    {
        $resolver = new ClassResolver([
            'BaseClass' => static function (array $json, MappingContext $context): string {
                $context->addError('accessed');

                return $json['next'];
            },
        ]);
        $context = new MappingContext([], ['flag' => true]);

        self::assertSame('ResolvedClass', $resolver->resolve('BaseClass', ['next' => 'ResolvedClass'], $context));
        self::assertSame(['accessed'], $context->getErrors());
    }
}
