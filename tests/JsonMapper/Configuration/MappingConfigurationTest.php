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
use MagicSunday\JsonMapper\Configuration\MappingConfiguration;
use MagicSunday\JsonMapper\Context\MappingContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class MappingConfigurationTest extends TestCase
{
    #[Test]
    public function itProvidesLenientDefaults(): void
    {
        $configuration = MappingConfiguration::lenient();

        self::assertFalse($configuration->isStrictMode());
        self::assertTrue($configuration->shouldCollectErrors());
        self::assertFalse($configuration->shouldTreatEmptyStringAsNull());
        self::assertFalse($configuration->shouldIgnoreUnknownProperties());
        self::assertFalse($configuration->shouldTreatNullAsEmptyCollection());
        self::assertSame(DateTimeInterface::ATOM, $configuration->getDefaultDateFormat());
        self::assertFalse($configuration->shouldAllowScalarToObjectCasting());
    }

    #[Test]
    public function itEnablesStrictMode(): void
    {
        $configuration = MappingConfiguration::strict();

        self::assertTrue($configuration->isStrictMode());
        self::assertTrue($configuration->shouldCollectErrors());
        self::assertFalse($configuration->shouldIgnoreUnknownProperties());
    }

    #[Test]
    public function itSupportsTogglingErrorCollection(): void
    {
        $configuration = MappingConfiguration::lenient()->withErrorCollection(false);

        self::assertFalse($configuration->isStrictMode());
        self::assertFalse($configuration->shouldCollectErrors());
    }

    #[Test]
    public function itSupportsEmptyStringConfiguration(): void
    {
        $configuration = MappingConfiguration::lenient()->withEmptyStringAsNull(true);

        self::assertTrue($configuration->shouldTreatEmptyStringAsNull());
        self::assertTrue($configuration->withEmptyStringAsNull(true)->shouldTreatEmptyStringAsNull());
    }

    #[Test]
    public function itSupportsExtendedFlags(): void
    {
        $configuration = MappingConfiguration::lenient()
            ->withIgnoreUnknownProperties(true)
            ->withTreatNullAsEmptyCollection(true)
            ->withDefaultDateFormat('d.m.Y H:i:s')
            ->withScalarToObjectCasting(true);

        self::assertTrue($configuration->shouldIgnoreUnknownProperties());
        self::assertTrue($configuration->shouldTreatNullAsEmptyCollection());
        self::assertSame('d.m.Y H:i:s', $configuration->getDefaultDateFormat());
        self::assertTrue($configuration->shouldAllowScalarToObjectCasting());
    }

    #[Test]
    public function itDerivesFromContext(): void
    {
        $context = new MappingContext([], [
            MappingContext::OPTION_STRICT_MODE                    => true,
            MappingContext::OPTION_COLLECT_ERRORS                 => true,
            MappingContext::OPTION_TREAT_EMPTY_STRING_AS_NULL     => false,
            MappingContext::OPTION_IGNORE_UNKNOWN_PROPERTIES      => true,
            MappingContext::OPTION_TREAT_NULL_AS_EMPTY_COLLECTION => true,
            MappingContext::OPTION_DEFAULT_DATE_FORMAT            => 'd.m.Y',
            MappingContext::OPTION_ALLOW_SCALAR_TO_OBJECT_CASTING => true,
        ]);

        $configuration = MappingConfiguration::fromContext($context);

        self::assertTrue($configuration->isStrictMode());
        self::assertTrue($configuration->shouldCollectErrors());
        self::assertFalse($configuration->shouldTreatEmptyStringAsNull());
        self::assertTrue($configuration->shouldIgnoreUnknownProperties());
        self::assertTrue($configuration->shouldTreatNullAsEmptyCollection());
        self::assertSame('d.m.Y', $configuration->getDefaultDateFormat());
        self::assertTrue($configuration->shouldAllowScalarToObjectCasting());
        self::assertSame(
            [
                MappingContext::OPTION_STRICT_MODE                    => true,
                MappingContext::OPTION_COLLECT_ERRORS                 => true,
                MappingContext::OPTION_TREAT_EMPTY_STRING_AS_NULL     => false,
                MappingContext::OPTION_IGNORE_UNKNOWN_PROPERTIES      => true,
                MappingContext::OPTION_TREAT_NULL_AS_EMPTY_COLLECTION => true,
                MappingContext::OPTION_DEFAULT_DATE_FORMAT            => 'd.m.Y',
                MappingContext::OPTION_ALLOW_SCALAR_TO_OBJECT_CASTING => true,
            ],
            $configuration->toOptions(),
        );
    }
}
