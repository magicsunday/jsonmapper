<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Context\MappingError;
use MagicSunday\Test\Classes\NestedOptionCarrier;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

use function array_map;

/**
 * Nested mapping used to rebuild a JsonMapperConfiguration from the context for every nested
 * object and hand it back to map(), which wrote it straight into the same context again.
 *
 * The round trip could only ever restore what was already there, and it was the mechanism by which
 * a custom option went missing before the write became a merge. Both halves are gone: the
 * configuration is translated into the context once, at the entry point, and nothing rebuilds it
 * per object.
 *
 * @internal
 */
final class NestedConfigurationRoundtripTest extends TestCase
{
    #[Test]
    public function itCarriesACustomOptionThroughEveryNestingLevel(): void
    {
        // The option bag is an extension point: a type handler may put its own keys there, and the
        // mapper's own configuration knows none of them. So a round trip through that
        // configuration could only ever lose them - which is exactly what happened until the write
        // became a merge, and cannot happen at all now that nothing rebuilds one.
        $payload = $this->getJsonAsObject('{"level2": {"level3": {"value": "deep"}}}');
        $context = new MappingContext($payload);
        $context->replaceOptions(['custom.key' => 'set-by-caller']);

        $result = $this->getJsonMapper()->map($payload, NestedOptionCarrier::class, null, $context);

        self::assertInstanceOf(NestedOptionCarrier::class, $result);
        self::assertSame('deep', $result->level2->level3->value, 'All three levels mapped.');
        self::assertSame(
            'set-by-caller',
            $context->getOption('custom.key'),
            'A custom option survives three levels of nesting.',
        );
    }

    #[Test]
    public function itAppliesAConfigurationToEveryNestingLevel(): void
    {
        // The counterpart: dropping the round trip must not drop the settings with it. Set once at
        // the entry point, the configuration has to still be in force three objects down.
        //
        // An unknown property rather than a missing one, deliberately: strict mode validates
        // MISSING properties on the root object only - that is issue #105, filed separately - so a
        // missing-property assertion here would fail for a reason that has nothing to do with the
        // round trip this test is about.
        $payload = $this->getJsonAsObject('{"level2": {"level3": {"value": "x", "surprise": 1}}}');

        $reported = $this->getJsonMapper(config: JsonMapperConfiguration::strict())
            ->mapWithReport($payload, NestedOptionCarrier::class);

        self::assertSame(
            ['$.level2.level3.surprise'],
            array_map(
                static fn (MappingError $error): string => $error->getPath(),
                $reported->getReport()->getErrors(),
            ),
            'Strict mode still applies at the innermost level.',
        );

        // And the option that switches it off reaches just as far - so this pins the setting
        // travelling, not merely something being reported.
        $ignored = $this->getJsonMapper(
            config: JsonMapperConfiguration::strict()->withIgnoreUnknownProperties(true),
        )->mapWithReport($payload, NestedOptionCarrier::class);

        self::assertFalse($ignored->getReport()->hasErrors(), 'And it can be switched off just as deep.');
    }
}
