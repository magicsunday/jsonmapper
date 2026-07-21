<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Value;

use LogicException;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Value\ClosureTypeHandler;
use MagicSunday\Test\Classes\Base;
use MagicSunday\Test\Classes\Simple;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\TypeInfo\Type;

use function get_object_vars;

/**
 * ClosureTypeHandler is what addType() wires a caller's closure into, so it is the shape of the
 * deprecated escape hatch that still has consumers. Both are pinned here: the handler's own
 * contract, and that registering through the legacy entry point still reaches it.
 *
 * @internal
 */
final class ClosureTypeHandlerTest extends TestCase
{
    #[Test]
    public function itDeclinesEveryTypeThatIsNotAnObjectType(): void
    {
        // A handler is registered for a CLASS, so a builtin target is not its business - and
        // asking a non-object type for a class name would raise. Declining is what leaves the
        // value to the strategy that can convert it.
        $handler = new ClosureTypeHandler(Simple::class, static fn (mixed $value): Simple => new Simple());

        self::assertFalse($handler->supports(Type::string(), 'anything'));
        self::assertFalse($handler->supports(Type::int(), 42));
        self::assertTrue($handler->supports(Type::object(Simple::class), []));
    }

    #[Test]
    public function itRefusesToConvertATypeItDeclined(): void
    {
        // Reachable only by calling the handler directly, which a consumer writing its own
        // conversion chain can do. Refusing loudly beats running a converter written for another
        // class against a value it never expected.
        $handler = new ClosureTypeHandler(Simple::class, static fn (mixed $value): Simple => new Simple());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/^Handler does not support type /');

        $handler->convert(Type::string(), 'anything', new MappingContext([]));
    }

    #[Test]
    public function itStillRoutesTheDeprecatedRegistrationThroughTheHandler(): void
    {
        // addType() is marked deprecated but remains public, and the documentation names it as the
        // escape hatch that outranks the built-in strategies. A pin for the legacy path, so that
        // removing it has to be a decision rather than an accident.
        $mapper = $this->getJsonMapper();
        $mapper->addType(
            Simple::class,
            static function (mixed $value): Simple {
                $simple         = new Simple();
                $simple->string = 'converted';

                return $simple;
            },
        );

        $result = $mapper->map(['simple' => ['string' => 'ignored']], Base::class);

        self::assertInstanceOf(Base::class, $result);

        // Read through get_object_vars(): the fixture's docblock declares the property
        // non-nullable, so a direct read is narrowed and the is-it-set assertion becomes vacuous.
        $properties = get_object_vars($result);

        self::assertInstanceOf(Simple::class, $properties['simple']);
        self::assertSame('converted', $properties['simple']->string, 'The registered closure ran.');
    }

    #[Test]
    public function itPassesTheMappingContextToATwoArgumentConverter(): void
    {
        // The handler adapts a one-argument closure by wrapping it; a two-argument one is used as
        // it stands. Pinned through the context reaching the converter, because the wrapper is
        // what would silently drop it.
        $seen = null;

        $handler = new ClosureTypeHandler(
            Simple::class,
            static function (mixed $value, MappingContext $context) use (&$seen): Simple {
                $seen = $context->getPath();

                return new Simple();
            },
        );

        $handler->convert(Type::object(Simple::class), [], new MappingContext([]));

        self::assertSame('$', $seen);
    }
}
