<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\JsonMapper;
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Exception\AmbiguousPropertyException;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\Test\Classes\AmbiguousSpellingNullableParent;
use MagicSunday\Test\Classes\AmbiguousSpellingParent;
use MagicSunday\Test\Classes\AmbiguousSpellingTarget;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Case and separators are folded, so several spellings of a key can name one property. A payload
 * that carries two of them applies the first one and records every later one as a failure at the
 * property, because a spelling the caller never inspected must not overwrite one it did.
 *
 * @internal
 */
#[CoversClass(JsonMapper::class)]
#[CoversNamespace('MagicSunday\JsonMapper')]
final class AmbiguousPropertySpellingTest extends TestCase
{
    /**
     * Pairs of keys that fold onto one property, in both payload orders.
     *
     * @return array<string, array{array<string, int|string>, string, list<string>}>
     */
    public static function collidingPayloadProvider(): array
    {
        return [
            'case only'                     => [['id' => 1, 'ID' => 999], 'id', ['id', 'ID']],
            'case only, reversed'           => [['ID' => 999, 'id' => 1], 'id', ['ID', 'id']],
            'snake case against camel case' => [['user_id' => 'a', 'userId' => 'b'], 'userId', ['user_id', 'userId']],
            'dash against camel case'       => [['userId' => 'a', 'user-id' => 'b'], 'userId', ['userId', 'user-id']],
        ];
    }

    /**
     * @param array<string, int|string> $payload  Payload with two spellings of one property
     * @param string                    $property Declared property both spellings name
     * @param list<string>              $keys     The two payload keys in payload order
     */
    #[Test]
    #[DataProvider('collidingPayloadProvider')]
    public function itRecordsTheCollisionAtThePropertyPath(array $payload, string $property, array $keys): void
    {
        $result = $this->getJsonMapper()->mapWithReport($payload, AmbiguousSpellingTarget::class);
        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'One collision, one record.');
        self::assertSame('$.' . $property, $errors[0]->getPath());

        $exception = $errors[0]->getException();

        self::assertInstanceOf(AmbiguousPropertyException::class, $exception);
        self::assertSame($property, $exception->getPropertyName());
        self::assertSame($keys, $exception->getPayloadKeys());
        self::assertSame(AmbiguousSpellingTarget::class, $exception->getClassName());
        self::assertSame('$.' . $property, $exception->getPath());
    }

    #[Test]
    public function itKeepsTheFirstSpellingAndDoesNotApplyTheLaterOne(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(['id' => 1, 'ID' => 999], AmbiguousSpellingTarget::class);

        $value = $result->getValue();

        self::assertInstanceOf(AmbiguousSpellingTarget::class, $value);
        self::assertSame(1, $value->id);
    }

    #[Test]
    public function itSurfacesTheCollisionAsATypeMismatchInsideANullableObject(): void
    {
        // A nullable object property is mapped through the union trial, which judges the member on
        // every recorded failure. The collision therefore reaches the report as a type mismatch of
        // the property, the same way any other failure recorded inside that lane does.
        $result = $this->getJsonMapper()->mapWithReport(
            ['child' => ['id' => 1, 'ID' => 999]],
            AmbiguousSpellingNullableParent::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertSame('$.child', $errors[0]->getPath());
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
    }

    #[Test]
    public function itNamesTheAppliedAndTheRejectedKeyForEveryLaterSpelling(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport(['id' => 1, 'ID' => 2, 'Id' => 3], AmbiguousSpellingTarget::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(2, $errors, 'Every spelling after the first one is a record of its own.');

        $second = $errors[0]->getException();
        $third  = $errors[1]->getException();

        self::assertInstanceOf(AmbiguousPropertyException::class, $second);
        self::assertInstanceOf(AmbiguousPropertyException::class, $third);

        // A record never carries the keys of the records before it, so its size does not grow
        // with the number of colliding spellings.
        self::assertSame(['id', 'ID'], $second->getPayloadKeys());
        self::assertSame(['id', 'Id'], $third->getPayloadKeys());

        $value = $result->getValue();

        self::assertInstanceOf(AmbiguousSpellingTarget::class, $value);
        self::assertSame(1, $value->id);
    }

    #[Test]
    public function itDoesNotUseALaterSpellingAsAFallbackForARejectedFirstValue(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport(['id' => 'not a number', 'ID' => 5], AmbiguousSpellingTarget::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(2, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertInstanceOf(AmbiguousPropertyException::class, $errors[1]->getException());

        $value = $result->getValue();

        self::assertInstanceOf(AmbiguousSpellingTarget::class, $value);
        self::assertSame(0, $value->id, 'The later spelling must not replace the rejected first value.');
    }

    #[Test]
    public function itDoesNotUseALaterSpellingAsAFallbackForAFirstValueThatIsRefused(): void
    {
        // A scalar for an object property is refused by the conversion itself, which is a
        // different route from the one a plain wrong scalar takes, and it must block the later
        // spelling in the same way.
        $result = $this->getJsonMapper()->mapWithReport(
            ['child' => 'scalar', 'Child' => ['id' => 5]],
            AmbiguousSpellingParent::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(2, $errors);
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
        self::assertInstanceOf(AmbiguousPropertyException::class, $errors[1]->getException());

        $value = $result->getValue();

        self::assertInstanceOf(AmbiguousSpellingParent::class, $value);
        self::assertFalse(isset($value->child), 'The later spelling must not replace the refused first value.');
    }

    #[Test]
    public function itKeepsPayloadTextOutOfTheMessage(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport(['id' => 1, 'ID' => 2], AmbiguousSpellingTarget::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);

        // The keys came from the payload and are exposed through the accessor only, so a message
        // that is forwarded to a log or a response never carries them.
        $exception = $errors[0]->getException();

        self::assertInstanceOf(AmbiguousPropertyException::class, $exception);
        self::assertSame(
            'Ambiguous property $.id on ' . AmbiguousSpellingTarget::class . '.',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function itRaisesInStrictModeWhenMapIsUsed(): void
    {
        $this->expectException(AmbiguousPropertyException::class);

        $this->getJsonMapper([], JsonMapperConfiguration::strict())
            ->map(['id' => 1, 'ID' => 999], AmbiguousSpellingTarget::class);
    }

    #[Test]
    public function itRecordsInLenientModeWithoutRaising(): void
    {
        $result = $this->getJsonMapper([], JsonMapperConfiguration::lenient())
            ->mapWithReport(['id' => 1, 'ID' => 999], AmbiguousSpellingTarget::class);

        self::assertCount(1, $result->getReport()->getErrors());
    }

    #[Test]
    public function itDoesNotReportDistinctProperties(): void
    {
        $result = $this->getJsonMapper()
            ->mapWithReport(['id' => 1, 'userId' => 'a'], AmbiguousSpellingTarget::class);

        self::assertCount(0, $result->getReport()->getErrors());
    }

    #[Test]
    public function itReportsTheCollisionOnTheNestedPath(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['child' => ['id' => 1, 'ID' => 999]],
            AmbiguousSpellingParent::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertSame('$.child.id', $errors[0]->getPath());
        self::assertInstanceOf(AmbiguousPropertyException::class, $errors[0]->getException());
    }
}
