<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Value;

use InvalidArgumentException;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Value\CustomTypeRegistry;
use MagicSunday\JsonMapper\Value\TypeHandlerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type\ObjectType;

use function is_string;

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
        $type    = new ObjectType('Foo');

        self::assertTrue($registry->supports($type, ['bar' => 'baz']));
        self::assertSame(['bar' => 'baz'], $registry->convert($type, ['bar' => 'baz'], $context));
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
        $type    = new ObjectType('Foo');
        $registry->convert($type, ['payload'], $context);

        self::assertSame(['called'], $context->getErrors());
    }

    #[Test]
    public function itSupportsCustomHandlers(): void
    {
        $registry = new CustomTypeRegistry();
        $registry->registerHandler(new class implements TypeHandlerInterface {
            public function supports(\Symfony\Component\TypeInfo\Type $type, mixed $value): bool
            {
                return $type instanceof ObjectType && $type->getClassName() === 'Foo';
            }

            public function convert(\Symfony\Component\TypeInfo\Type $type, mixed $value, MappingContext $context): mixed
            {
                if (!is_string($value)) {
                    throw new InvalidArgumentException('Expected string value.');
                }

                $context->addError('converted');

                return 'handled-' . $value;
            }
        });

        $context = new MappingContext([]);
        $type    = new ObjectType('Foo');

        self::assertTrue($registry->supports($type, 'value'));
        self::assertSame('handled-value', $registry->convert($type, 'value', $context));
        self::assertSame(['converted'], $context->getErrors());
    }
}
