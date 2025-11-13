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
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Context\MappingContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
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
            ->withScalarToObjectCasting(true);

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
            ->withScalarToObjectCasting(true);

        $restored = JsonMapperConfiguration::fromArray($configuration->toArray());

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
        ]);

        $configuration = JsonMapperConfiguration::fromContext($context);

        self::assertTrue($configuration->isStrictMode());
        self::assertFalse($configuration->shouldCollectErrors());
        self::assertTrue($configuration->shouldTreatEmptyStringAsNull());
        self::assertTrue($configuration->shouldIgnoreUnknownProperties());
        self::assertTrue($configuration->shouldTreatNullAsEmptyCollection());
        self::assertSame('d.m.Y', $configuration->getDefaultDateFormat());
        self::assertTrue($configuration->shouldAllowScalarToObjectCasting());
        self::assertSame([
            MappingContext::OPTION_STRICT_MODE                    => true,
            MappingContext::OPTION_COLLECT_ERRORS                 => false,
            MappingContext::OPTION_TREAT_EMPTY_STRING_AS_NULL     => true,
            MappingContext::OPTION_IGNORE_UNKNOWN_PROPERTIES      => true,
            MappingContext::OPTION_TREAT_NULL_AS_EMPTY_COLLECTION => true,
            MappingContext::OPTION_DEFAULT_DATE_FORMAT            => 'd.m.Y',
            MappingContext::OPTION_ALLOW_SCALAR_TO_OBJECT_CASTING => true,
        ], $configuration->toOptions());
    }
}
