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
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Context\MappingError;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\Test\Classes\AbortChainOuter;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\Test;

use function array_map;

/**
 * An aborting run raises the failure of the innermost value, and every enclosing level lets it
 * pass. Recording it again on the way out filed one record per nesting level for one rejected
 * value.
 *
 * @internal
 */
#[CoversClass(JsonMapper::class)]
#[CoversNamespace('MagicSunday\JsonMapper')]
final class AbortingRunRecordTest extends TestCase
{
    #[Test]
    public function itRecordsARejectedValueOnceHoweverDeepItSits(): void
    {
        $payload = ['middle' => ['inner' => ['count' => ['deep' => 'array']]]];
        $config  = JsonMapperConfiguration::strict();
        $context = new MappingContext($payload, $config->toOptions());

        try {
            $this->getJsonMapper(config: $config)->map($payload, AbortChainOuter::class, null, $context, $config);

            self::fail('A strict run that aborts has to raise the rejected value.');
        } catch (TypeMismatchException $exception) {
            self::assertSame('$.middle.inner.count', $exception->getPath());
        }

        self::assertSame(
            ['$.middle.inner.count'],
            array_map(
                static fn (MappingError $error): string => $error->getPath(),
                $context->getErrorRecords(),
            ),
        );
    }
}
