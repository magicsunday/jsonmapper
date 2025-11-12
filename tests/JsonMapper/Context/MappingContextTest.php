<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper\Context;

use MagicSunday\JsonMapper\Context\MappingContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class MappingContextTest extends TestCase
{
    #[Test]
    public function itTracksPathSegments(): void
    {
        $context = new MappingContext(['root']);

        self::assertSame('$', $context->getPath());

        $result = $context->withPathSegment('items', function (MappingContext $child): string {
            self::assertSame('$.items', $child->getPath());

            $child->withPathSegment(0, function (MappingContext $nested): void {
                self::assertSame('$.items.0', $nested->getPath());
            });

            return 'done';
        });

        self::assertSame('done', $result);
        self::assertSame('$', $context->getPath());
    }

    #[Test]
    public function itCollectsErrors(): void
    {
        $context = new MappingContext(['root']);
        $context->addError('failure');

        self::assertSame(['failure'], $context->getErrors());
    }

    #[Test]
    public function itExposesOptions(): void
    {
        $context = new MappingContext(['root'], ['flag' => true]);

        self::assertSame(['flag' => true], $context->getOptions());
        self::assertTrue($context->getOption('flag'));
        self::assertSame('fallback', $context->getOption('missing', 'fallback'));
    }

    #[Test]
    public function itProvidesTypedOptionAccessors(): void
    {
        $context = new MappingContext(['root'], [
            MappingContext::OPTION_IGNORE_UNKNOWN_PROPERTIES      => true,
            MappingContext::OPTION_TREAT_NULL_AS_EMPTY_COLLECTION => true,
            MappingContext::OPTION_DEFAULT_DATE_FORMAT            => 'd.m.Y',
            MappingContext::OPTION_ALLOW_SCALAR_TO_OBJECT_CASTING => true,
        ]);

        self::assertTrue($context->shouldIgnoreUnknownProperties());
        self::assertTrue($context->shouldTreatNullAsEmptyCollection());
        self::assertSame('d.m.Y', $context->getDefaultDateFormat());
        self::assertTrue($context->shouldAllowScalarToObjectCasting());
    }
}
