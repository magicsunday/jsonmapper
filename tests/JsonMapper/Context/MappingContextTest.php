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

    #[Test]
    public function itDefersTheAbortDecisionToStrictModeUntilOverridden(): void
    {
        // The only accessor with a non-constant default: absent, it answers with strict mode, so
        // map() keeps aborting without anyone having to set the option. Both fallback directions
        // are pinned - a default of plain false would leave strict map() collecting silently, and
        // a default of plain true would make mapWithReport()'s override the only thing standing
        // between a lenient caller and an exception.
        self::assertTrue((new MappingContext(['root'], [
            MappingContext::OPTION_STRICT_MODE => true,
        ]))->shouldAbortOnError(), 'Absent option defers to strict mode.');

        self::assertFalse((new MappingContext(['root']))->shouldAbortOnError(), 'Lenient never aborts.');

        // Set explicitly, it wins over strict mode in BOTH directions - that override is the whole
        // mechanism by which mapWithReport() collects what map() would have raised.
        self::assertFalse((new MappingContext(['root'], [
            MappingContext::OPTION_STRICT_MODE       => true,
            MappingContext::OPTION_ABORT_ON_ERROR    => false,
        ]))->shouldAbortOnError(), 'The explicit option overrides strict mode.');

        self::assertTrue((new MappingContext(['root'], [
            MappingContext::OPTION_ABORT_ON_ERROR => true,
        ]))->shouldAbortOnError(), 'The explicit option overrides lenient mode too.');
    }
}
