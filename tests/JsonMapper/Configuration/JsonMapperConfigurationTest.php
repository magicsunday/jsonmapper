<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Configuration;

use DateTimeInterface;
use InvalidArgumentException;
use MagicSunday\JsonMapper;
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Context\MappingContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_replace;

/**
 * @internal
 */
#[CoversClass(JsonMapper::class)]
#[CoversNamespace('MagicSunday\JsonMapper')]
final class JsonMapperConfigurationTest extends TestCase
{
    #[Test]
    public function itProvidesLenientDefaults(): void
    {
        $configuration = JsonMapperConfiguration::lenient();

        self::assertFalse($configuration->isStrictMode());
        self::assertTrue($configuration->shouldCollectErrors());
        self::assertFalse($configuration->shouldTreatEmptyStringAsNull());
        self::assertFalse($configuration->shouldIgnoreUnknownProperties());
        self::assertFalse($configuration->shouldTreatNullAsEmptyCollection());
        self::assertSame(DateTimeInterface::ATOM, $configuration->getDefaultDateFormat());
        self::assertFalse($configuration->shouldAllowScalarToObjectCasting());
    }

    #[Test]
    public function itProvidesStrictPreset(): void
    {
        $configuration = JsonMapperConfiguration::strict();

        self::assertTrue($configuration->isStrictMode());
        self::assertTrue($configuration->shouldCollectErrors());
        self::assertFalse($configuration->shouldIgnoreUnknownProperties());
    }

    #[Test]
    public function itSupportsImmutableUpdates(): void
    {
        $configuration = JsonMapperConfiguration::lenient();

        $updated = $configuration
            ->withStrictMode(true)
            ->withErrorCollection(false)
            ->withEmptyStringAsNull(true)
            ->withIgnoreUnknownProperties(true)
            ->withTreatNullAsEmptyCollection(true)
            ->withDefaultDateFormat('d.m.Y H:i:s')
            ->withScalarToObjectCasting(true)
            ->withMaxErrors(25);

        self::assertSame(MappingContext::DEFAULT_MAX_ERRORS, $configuration->getMaxErrors());
        self::assertSame(25, $updated->getMaxErrors());
        self::assertFalse($configuration->isStrictMode());
        self::assertTrue($configuration->shouldCollectErrors());
        self::assertFalse($configuration->shouldTreatEmptyStringAsNull());
        self::assertFalse($configuration->shouldIgnoreUnknownProperties());
        self::assertFalse($configuration->shouldTreatNullAsEmptyCollection());
        self::assertSame(DateTimeInterface::ATOM, $configuration->getDefaultDateFormat());
        self::assertFalse($configuration->shouldAllowScalarToObjectCasting());

        self::assertTrue($updated->isStrictMode());
        self::assertFalse($updated->shouldCollectErrors());
        self::assertTrue($updated->shouldTreatEmptyStringAsNull());
        self::assertTrue($updated->shouldIgnoreUnknownProperties());
        self::assertTrue($updated->shouldTreatNullAsEmptyCollection());
        self::assertSame('d.m.Y H:i:s', $updated->getDefaultDateFormat());
        self::assertTrue($updated->shouldAllowScalarToObjectCasting());
    }

    #[Test]
    public function itSerializesAndRestoresFromArrays(): void
    {
        $configuration = JsonMapperConfiguration::lenient()
            ->withStrictMode(true)
            ->withErrorCollection(false)
            ->withEmptyStringAsNull(true)
            ->withIgnoreUnknownProperties(true)
            ->withTreatNullAsEmptyCollection(true)
            ->withDefaultDateFormat('d.m.Y')
            ->withScalarToObjectCasting(true)
            ->withMaxErrors(25);

        $restored = JsonMapperConfiguration::fromArray($configuration->toArray());

        self::assertSame(25, $restored->getMaxErrors());

        self::assertTrue($restored->isStrictMode());
        self::assertFalse($restored->shouldCollectErrors());
        self::assertTrue($restored->shouldTreatEmptyStringAsNull());
        self::assertTrue($restored->shouldIgnoreUnknownProperties());
        self::assertTrue($restored->shouldTreatNullAsEmptyCollection());
        self::assertSame('d.m.Y', $restored->getDefaultDateFormat());
        self::assertTrue($restored->shouldAllowScalarToObjectCasting());
    }

    #[Test]
    public function itRestoresFromContext(): void
    {
        $context = new MappingContext([], [
            MappingContext::OPTION_STRICT_MODE                    => true,
            MappingContext::OPTION_COLLECT_ERRORS                 => false,
            MappingContext::OPTION_TREAT_EMPTY_STRING_AS_NULL     => true,
            MappingContext::OPTION_IGNORE_UNKNOWN_PROPERTIES      => true,
            MappingContext::OPTION_TREAT_NULL_AS_EMPTY_COLLECTION => true,
            MappingContext::OPTION_DEFAULT_DATE_FORMAT            => 'd.m.Y',
            MappingContext::OPTION_ALLOW_SCALAR_TO_OBJECT_CASTING => true,
            MappingContext::OPTION_DEFAULT_TIMEZONE               => 'Asia/Tokyo',
            MappingContext::OPTION_MAX_ERRORS                     => 25,
            MappingContext::OPTION_MAX_DEPTH                      => 12,
        ]);

        $configuration = JsonMapperConfiguration::fromContext($context);

        self::assertSame(25, $configuration->getMaxErrors());
        self::assertSame(12, $configuration->getMaxDepth());

        self::assertTrue($configuration->isStrictMode());
        self::assertFalse($configuration->shouldCollectErrors());
        self::assertTrue($configuration->shouldTreatEmptyStringAsNull());
        self::assertTrue($configuration->shouldIgnoreUnknownProperties());
        self::assertTrue($configuration->shouldTreatNullAsEmptyCollection());
        self::assertSame('d.m.Y', $configuration->getDefaultDateFormat());
        self::assertTrue($configuration->shouldAllowScalarToObjectCasting());
        self::assertSame('Asia/Tokyo', $configuration->getDefaultTimezone());
        self::assertSame([
            MappingContext::OPTION_STRICT_MODE                    => true,
            MappingContext::OPTION_COLLECT_ERRORS                 => false,
            MappingContext::OPTION_TREAT_EMPTY_STRING_AS_NULL     => true,
            MappingContext::OPTION_IGNORE_UNKNOWN_PROPERTIES      => true,
            MappingContext::OPTION_TREAT_NULL_AS_EMPTY_COLLECTION => true,
            MappingContext::OPTION_DEFAULT_DATE_FORMAT            => 'd.m.Y',
            MappingContext::OPTION_ALLOW_SCALAR_TO_OBJECT_CASTING => true,
            MappingContext::OPTION_DEFAULT_TIMEZONE               => 'Asia/Tokyo',
            MappingContext::OPTION_MAX_ERRORS                     => 25,
            MappingContext::OPTION_MAX_DEPTH                      => 12,
        ], $configuration->toOptions());
    }

    #[Test]
    public function itKeepsEveryOtherSettingWhenTheCeilingChanges(): void
    {
        $configuration = JsonMapperConfiguration::strict()
            ->withErrorCollection(false)
            ->withEmptyStringAsNull(true)
            ->withIgnoreUnknownProperties(true)
            ->withTreatNullAsEmptyCollection(true)
            ->withDefaultDateFormat('d.m.Y')
            ->withScalarToObjectCasting(true)
            ->withDefaultTimezone('Asia/Tokyo');

        $changed = $configuration->withMaxErrors(7);

        self::assertSame(7, $changed->getMaxErrors());
        self::assertSame(
            array_replace($configuration->toArray(), ['maxErrors' => 7]),
            $changed->toArray(),
        );
    }

    /**
     * @param callable(JsonMapperConfiguration): JsonMapperConfiguration $setting Changes exactly one setting
     */
    #[Test]
    #[DataProvider('singleSettingProvider')]
    public function itKeepsEachSettingInPlaceWhenTheCeilingChanges(callable $setting): void
    {
        // One setting differs from the default at a time. The wither hands the settings on by
        // position, and several of them are booleans, so a configuration with all of them set the
        // same way could not show two of them being swapped.
        $configuration = $setting(JsonMapperConfiguration::lenient());

        self::assertSame(
            array_replace($configuration->toArray(), ['maxErrors' => 7]),
            $configuration->withMaxErrors(7)->toArray(),
        );
    }

    /**
     * @return array<string, array{0: callable(JsonMapperConfiguration): JsonMapperConfiguration}>
     */
    public static function singleSettingProvider(): array
    {
        return [
            'strict mode'          => [static fn (JsonMapperConfiguration $c): JsonMapperConfiguration => $c->withStrictMode(true)],
            'error collection'     => [static fn (JsonMapperConfiguration $c): JsonMapperConfiguration => $c->withErrorCollection(false)],
            'empty string as null' => [static fn (JsonMapperConfiguration $c): JsonMapperConfiguration => $c->withEmptyStringAsNull(true)],
            'ignore unknown'       => [static fn (JsonMapperConfiguration $c): JsonMapperConfiguration => $c->withIgnoreUnknownProperties(true)],
            'null as empty list'   => [static fn (JsonMapperConfiguration $c): JsonMapperConfiguration => $c->withTreatNullAsEmptyCollection(true)],
            'scalar to object'     => [static fn (JsonMapperConfiguration $c): JsonMapperConfiguration => $c->withScalarToObjectCasting(true)],
            'date format'          => [static fn (JsonMapperConfiguration $c): JsonMapperConfiguration => $c->withDefaultDateFormat('d.m.Y')],
            'timezone'             => [static fn (JsonMapperConfiguration $c): JsonMapperConfiguration => $c->withDefaultTimezone('Asia/Tokyo')],
        ];
    }

    #[Test]
    public function itRejectsACeilingBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);

        JsonMapperConfiguration::lenient()->withMaxErrors(0);
    }

    #[Test]
    public function itRejectsACeilingBelowOneWhenCreatedDirectlyToo(): void
    {
        // The getter would otherwise report a ceiling that the mapper never applies.
        $this->expectException(InvalidArgumentException::class);

        new JsonMapperConfiguration(maxErrors: 0);
    }

    #[Test]
    public function itFallsBackToTheDefaultCeilingWhenARestoredValueIsNotUsable(): void
    {
        // Sanitised rather than rejected, like the other restored values: a restore should not
        // fail on a setting that can be defaulted.
        foreach ([0, -3, 'many', null, 2.5, true] as $unusable) {
            self::assertSame(
                MappingContext::DEFAULT_MAX_ERRORS,
                JsonMapperConfiguration::fromArray(['maxErrors' => $unusable])->getMaxErrors(),
                'Unusable ceiling: ' . var_export($unusable, true),
            );
        }
    }

    #[Test]
    public function itRestoresACeilingThatArrivedAsANumericString(): void
    {
        // Settings read from a file or the environment carry numbers as strings. Falling back to
        // the default for one would loosen the ceiling the caller had chosen.
        self::assertSame(50, JsonMapperConfiguration::fromArray(['maxErrors' => '50'])->getMaxErrors());
    }
}
