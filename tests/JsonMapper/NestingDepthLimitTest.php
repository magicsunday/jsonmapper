<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use InvalidArgumentException;
use MagicSunday\JsonMapper;
use MagicSunday\JsonMapper\Configuration\JsonMapperConfiguration;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Exception\DepthLimitExceededException;
use MagicSunday\Test\Classes\DepthChainLink;
use MagicSunday\Test\Classes\DepthChainNode;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\Test;

use function memory_get_peak_usage;
use function memory_reset_peak_usage;

/**
 * A payload cannot nest a mapping run deeper than the configured limit.
 *
 * @internal
 */
#[CoversClass(JsonMapper::class)]
#[CoversNamespace('MagicSunday\JsonMapper')]
final class NestingDepthLimitTest extends TestCase
{
    /**
     * Builds a payload of nested "child" objects.
     *
     * @param int $levels Number of objects nested below the root
     *
     * @return array<string, mixed>
     */
    private static function chain(int $levels): array
    {
        $payload = ['name' => 'leaf'];

        for ($level = 0; $level < $levels; ++$level) {
            $payload = ['name' => 'link', 'child' => $payload];
        }

        return $payload;
    }

    #[Test]
    public function itMapsAChainUpToTheLimit(): void
    {
        // The deepest path of three nested objects is $.child.child.child.name, four levels.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxDepth(4))
            ->mapWithReport(self::chain(3), DepthChainLink::class);

        self::assertSame(0, $result->getReport()->getErrorCount());

        $value = $result->getValue();

        self::assertInstanceOf(DepthChainLink::class, $value);
        self::assertSame('leaf', $value->child->child->child->name);
    }

    #[Test]
    public function itRecordsTheLevelBeyondTheLimitAndKeepsWhatWasMappedBefore(): void
    {
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxDepth(4))
            ->mapWithReport(self::chain(4), DepthChainLink::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);

        $exception = $errors[0]->getException();

        self::assertInstanceOf(DepthLimitExceededException::class, $exception);
        self::assertSame(4, $exception->getLimit());
        self::assertSame('$.child.child.child.child.name', $exception->getPath());
        // The record is filed where the failure was caught, the object that held the refused level.
        self::assertSame('$.child.child.child.child', $errors[0]->getPath());

        $value = $result->getValue();

        self::assertInstanceOf(DepthChainLink::class, $value);
        self::assertSame('link', $value->child->child->child->name);
        self::assertFalse(isset($value->child->child->child->child), 'The object that held the refused level is not kept.');
    }

    #[Test]
    public function itNamesTheLimitInsideANullableObjectProperty(): void
    {
        // A nullable object property is a union, whose member is judged on what converting it
        // recorded. A depth failure is not a statement about the member and must reach the report as
        // it is, not as a type mismatch of the property.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxDepth(4))
            ->mapWithReport(self::chain(4), DepthChainNode::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);

        $exception = $errors[0]->getException();

        self::assertInstanceOf(DepthLimitExceededException::class, $exception);
        self::assertSame(4, $exception->getLimit());
    }

    #[Test]
    public function itRaisesInStrictModeWhenMapIsUsed(): void
    {
        $mapper = $this->getJsonMapper(config: JsonMapperConfiguration::strict()->withMaxDepth(2));

        $this->expectException(DepthLimitExceededException::class);

        $mapper->map(self::chain(3), DepthChainLink::class);
    }

    #[Test]
    public function itRaisesInStrictModeInsideANullableObjectProperty(): void
    {
        $mapper = $this->getJsonMapper(config: JsonMapperConfiguration::strict()->withMaxDepth(2));

        $this->expectException(DepthLimitExceededException::class);

        $mapper->map(self::chain(3), DepthChainNode::class);
    }

    #[Test]
    public function itLimitsTheDepthOfAHostilePayloadWithoutExhaustingMemory(): void
    {
        $payload = self::chain(2000);
        $mapper  = $this->getJsonMapper(config: JsonMapperConfiguration::lenient());

        memory_reset_peak_usage();

        $result = $mapper->mapWithReport($payload, DepthChainNode::class);

        // A failure per level kept with its own stack trace costs memory that grows with the square
        // of the depth, which is hundreds of megabytes at a few hundred levels. The default limit is
        // what keeps this run small.
        self::assertLessThan(96 * 1024 * 1024, memory_get_peak_usage());
        self::assertSame(1, $result->getReport()->getErrorCount());
        self::assertInstanceOf(
            DepthLimitExceededException::class,
            $result->getReport()->getErrors()[0]->getException(),
        );
    }

    #[Test]
    public function itStartsFromTheDefaultLimit(): void
    {
        self::assertSame(MappingContext::DEFAULT_MAX_DEPTH, JsonMapperConfiguration::lenient()->getMaxDepth());
        self::assertSame(MappingContext::DEFAULT_MAX_DEPTH, (new MappingContext([]))->getMaxDepth());
    }

    #[Test]
    public function itRejectsALimitBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);

        JsonMapperConfiguration::lenient()->withMaxDepth(0);
    }

    #[Test]
    public function itCarriesTheLimitThroughTheConfigurationArray(): void
    {
        $configuration = JsonMapperConfiguration::lenient()->withMaxDepth(7);

        self::assertSame(7, $configuration->toArray()['maxDepth']);
        self::assertSame(7, JsonMapperConfiguration::fromArray($configuration->toArray())->getMaxDepth());
        self::assertSame(7, $configuration->toOptions()[MappingContext::OPTION_MAX_DEPTH]);
    }

    #[Test]
    public function itFallsBackToTheDefaultForAnUnusableRestoredLimit(): void
    {
        self::assertSame(
            MappingContext::DEFAULT_MAX_DEPTH,
            JsonMapperConfiguration::fromArray(['maxDepth' => 0])->getMaxDepth(),
        );
        self::assertSame(
            MappingContext::DEFAULT_MAX_DEPTH,
            JsonMapperConfiguration::fromArray(['maxDepth' => 'deep'])->getMaxDepth(),
        );
    }

    #[Test]
    public function itKeepsTheOtherSettingsWhenTheLimitIsChanged(): void
    {
        $configuration = JsonMapperConfiguration::strict()->withMaxErrors(5)->withMaxDepth(9);

        self::assertTrue($configuration->isStrictMode());
        self::assertSame(5, $configuration->getMaxErrors());
        self::assertSame(9, $configuration->getMaxDepth());
        self::assertSame(9, $configuration->withMaxErrors(6)->getMaxDepth());
    }

    #[Test]
    public function itRestoresThePathAfterTheLimitWasExceeded(): void
    {
        $context = new MappingContext([], [MappingContext::OPTION_MAX_DEPTH => 1]);

        try {
            $context->withPathSegment('a', static function (MappingContext $inner): void {
                $inner->withPathSegment('b', static function (): void {
                });
            });
            self::fail('The second level should have exceeded the limit.');
        } catch (DepthLimitExceededException $exception) {
            self::assertSame('$.a.b', $exception->getPath());
        }

        self::assertSame('$', $context->getPath());
    }
}
