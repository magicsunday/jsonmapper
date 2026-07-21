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
use MagicSunday\JsonMapper\Context\MappingError;
use MagicSunday\Test\Fixtures\Shapes\RequiredShapesHolder;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

use function array_map;

/**
 * Strict mode reports a property the payload did not supply, and "did it have to be supplied" is a
 * question about the declaration rather than about the payload. A property that accepts null needs
 * nothing; one that cannot be null and has no default is left uninitialised, which is the state
 * that makes reading it back raise.
 *
 * @internal
 */
final class RequiredPropertyShapeTest extends TestCase
{
    #[Test]
    public function itReportsOnlyThePropertyThatNoAbsentValueSatisfies(): void
    {
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())
            ->mapWithReport([], RequiredShapesHolder::class);

        self::assertSame(
            ['$.required'],
            array_map(
                static fn (MappingError $error): string => $error->getPath(),
                $result->getReport()->getErrors(),
            ),
            'The union naming null accepts an absent value; the intersection cannot.',
        );
    }
}
