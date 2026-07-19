<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Exception;

use MagicSunday\JsonMapper\Exception\CollectionMappingException;
use MagicSunday\JsonMapper\Exception\MappingException;
use MagicSunday\JsonMapper\Exception\MissingConstructorArgumentException;
use MagicSunday\JsonMapper\Exception\MissingPropertyException;
use MagicSunday\JsonMapper\Exception\ReadonlyPropertyException;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\JsonMapper\Exception\UnknownPropertyException;
use MagicSunday\Test\Classes\Person;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function method_exists;

/**
 * A mapping message embeds the internal class name and the property name the payload supplied, so
 * forwarding it to an API client discloses the DTO namespace and reflects an attacker-chosen string
 * back unescaped. The documentation says to build client-facing text from the structured getters
 * instead - which is only advice a consumer can follow if every exception HAS them.
 *
 * Two did not: the constructor-argument and readonly-property failures carried their class and
 * member names in the message alone, so following the advice meant parsing the very string the
 * advice says not to trust.
 *
 * @internal
 */
final class StructuredExceptionDataTest extends TestCase
{
    #[Test]
    public function itExposesTheConstructorArgumentWithoutParsingTheMessage(): void
    {
        $exception = new MissingConstructorArgumentException('$.user', 'name', Person::class);

        self::assertSame('name', $exception->getArgumentName());
        self::assertSame(Person::class, $exception->getClassName());
        self::assertSame('$.user', $exception->getPath());
    }

    #[Test]
    public function itExposesTheReadonlyPropertyWithoutParsingTheMessage(): void
    {
        $exception = new ReadonlyPropertyException('$.user.id', 'id', Person::class);

        self::assertSame('id', $exception->getPropertyName());
        self::assertSame(Person::class, $exception->getClassName());
        self::assertSame('$.user.id', $exception->getPath());
    }

    /**
     * Every mapping exception, and the accessors a consumer needs to rebuild the failure without
     * touching getMessage().
     *
     * @return array<string, array{MappingException, list<string>}>
     */
    public static function structuredAccessorProvider(): array
    {
        return [
            'missing constructor argument' => [
                new MissingConstructorArgumentException('$', 'name', Person::class),
                ['getPath', 'getArgumentName', 'getClassName'],
            ],
            'readonly property' => [
                new ReadonlyPropertyException('$', 'id', Person::class),
                ['getPath', 'getPropertyName', 'getClassName'],
            ],
            'missing property' => [
                new MissingPropertyException('$', 'name', Person::class),
                ['getPath', 'getPropertyName', 'getClassName'],
            ],
            'unknown property' => [
                new UnknownPropertyException('$', 'name', Person::class),
                ['getPath', 'getPropertyName', 'getClassName'],
            ],
            'type mismatch' => [
                new TypeMismatchException('$', 'int', 'string'),
                ['getPath', 'getExpectedType', 'getActualType'],
            ],
            'collection mapping' => [
                new CollectionMappingException('$', 'string'),
                ['getPath', 'getActualType'],
            ],
        ];
    }

    /**
     * @param MappingException $exception Exception under inspection
     * @param list<string>     $accessors Methods a consumer must be able to call
     */
    #[Test]
    #[DataProvider('structuredAccessorProvider')]
    public function itLetsEveryFailureBeRebuiltFromAccessors(MappingException $exception, array $accessors): void
    {
        // Asserted as an inventory rather than one test per exception: the guarantee the docs give
        // is about the whole hierarchy, so a new exception type added without accessors is exactly
        // what should fail here.
        foreach ($accessors as $accessor) {
            self::assertTrue(
                method_exists($exception, $accessor),
                $exception::class . ' must expose ' . $accessor . '() so a client message needs no parsing.',
            );
        }
    }
}
