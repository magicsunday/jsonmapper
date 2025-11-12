<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Configuration;

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
    }

    #[Test]
    public function itEnablesStrictMode(): void
    {
        $configuration = MappingConfiguration::strict();

        self::assertTrue($configuration->isStrictMode());
        self::assertTrue($configuration->shouldCollectErrors());
    }

    #[Test]
    public function itSupportsTogglingErrorCollection(): void
    {
        $configuration = MappingConfiguration::lenient()->withErrorCollection(false);

        self::assertFalse($configuration->isStrictMode());
        self::assertFalse($configuration->shouldCollectErrors());
    }

    #[Test]
    public function itDerivesFromContext(): void
    {
        $context = new MappingContext([], [
            MappingContext::OPTION_STRICT_MODE    => true,
            MappingContext::OPTION_COLLECT_ERRORS => true,
        ]);

        $configuration = MappingConfiguration::fromContext($context);

        self::assertTrue($configuration->isStrictMode());
        self::assertTrue($configuration->shouldCollectErrors());
        self::assertSame(
            [
                MappingContext::OPTION_STRICT_MODE    => true,
                MappingContext::OPTION_COLLECT_ERRORS => true,
            ],
            $configuration->toOptions(),
        );
    }
}
