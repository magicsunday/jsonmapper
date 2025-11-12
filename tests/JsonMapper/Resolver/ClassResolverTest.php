<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Resolver;

use DomainException;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Resolver\ClassResolver;
use MagicSunday\Test\Fixtures\Resolver\DummyBaseClass;
use MagicSunday\Test\Fixtures\Resolver\DummyMappedClass;
use MagicSunday\Test\Fixtures\Resolver\DummyResolvedClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * @internal
 */
final class ClassResolverTest extends TestCase
{
    #[Test]
    public function itResolvesMappedClassNames(): void
    {
        $resolver = new ClassResolver([DummyBaseClass::class => DummyMappedClass::class]);
        $context  = new MappingContext([]);

        self::assertSame(DummyMappedClass::class, $resolver->resolve(DummyBaseClass::class, ['json'], $context));
    }

    #[Test]
    public function itSupportsClosuresWithSingleArgument(): void
    {
        $resolver = new ClassResolver([DummyBaseClass::class => static fn (): string => DummyMappedClass::class]);
        $context  = new MappingContext([]);

        self::assertSame(DummyMappedClass::class, $resolver->resolve(DummyBaseClass::class, ['json'], $context));
    }

    #[Test]
    public function itSupportsClosuresReceivingContext(): void
    {
        $resolver = new ClassResolver([
            DummyBaseClass::class => static function (mixed $json, MappingContext $context): string {
                $context->addError('accessed');

                return DummyResolvedClass::class;
            },
        ]);
        $context = new MappingContext([], ['flag' => true]);

        self::assertSame(DummyResolvedClass::class, $resolver->resolve(DummyBaseClass::class, ['payload'], $context));
        self::assertSame(['accessed'], $context->getErrors());
    }

    #[Test]
    public function itRejectsResolversReturningNonStrings(): void
    {
        $resolver = new ClassResolver();

        $classMap = new ReflectionProperty(ClassResolver::class, 'classMap');
        $classMap->setAccessible(true);
        $classMap->setValue($resolver, [
            DummyBaseClass::class => static fn (): int => 123,
        ]);

        $context = new MappingContext([]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Class resolver for ' . DummyBaseClass::class . ' must return a class-string, int given.');

        $resolver->resolve(DummyBaseClass::class, ['json'], $context);
    }
}
