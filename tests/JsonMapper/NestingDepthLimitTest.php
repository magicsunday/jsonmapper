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
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\Test\Classes\DepthBufferHolder;
use MagicSunday\Test\Classes\DepthChainLink;
use MagicSunday\Test\Classes\DepthChainNode;
use MagicSunday\Test\Classes\DepthCollectionA;
use MagicSunday\Test\Classes\DepthCollectionB;
use MagicSunday\Test\Classes\DepthConstructorHolder;
use MagicSunday\Test\Classes\DepthMutualA;
use MagicSunday\Test\Classes\DepthMutualB;
use MagicSunday\Test\Classes\DepthProbeOuter;
use MagicSunday\Test\Classes\DepthRecordingUnionHolder;
use MagicSunday\Test\Classes\DepthRestoreHolder;
use MagicSunday\Test\Classes\DepthScalarUnionHolder;
use MagicSunday\Test\Classes\DepthStaleOuter;
use MagicSunday\Test\Classes\DepthUnionCollectionA;
use MagicSunday\Test\Classes\DepthUnionCollectionB;
use MagicSunday\Test\Classes\DepthUnionHolder;
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
     * Objects built for a payload that fails at the limit, far below what doubling per level reaches.
     */
    private const int LINEAR_WORK_BOUND = 200;

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
    public function itStillTriesAMemberThatDoesNotDescendAfterADepthFailure(): void
    {
        // The member that needs no further levels accepts the payload as it is, so a depth failure
        // of the object member is no reason to refuse the property.
        $payload = ['value' => self::chain(5)];

        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxDepth(3))
            ->mapWithReport($payload, DepthUnionHolder::class);

        self::assertSame(0, $result->getReport()->getErrorCount());

        $value = $result->getValue();

        self::assertInstanceOf(DepthUnionHolder::class, $value);
        self::assertSame($payload['value'], $value->value);
    }

    #[Test]
    public function itNamesTheLimitWhenTheReportCouldNotKeepTheFailure(): void
    {
        // The invalid integer fills the buffer the member is tried in, so the depth failure behind
        // it is counted but not kept. It must still be the failure the property is refused with.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxErrors(1)->withMaxDepth(2))
            ->mapWithReport(['p' => ['bad' => [], 'child' => ['name' => 'leaf']]], DepthBufferHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertNotSame([], $errors);
        self::assertInstanceOf(DepthLimitExceededException::class, $errors[0]->getException());
    }

    #[Test]
    public function itNamesTheLimitWhenTheMemberFailsToBeBuiltAfterwards(): void
    {
        // The member records the depth failure while its constructor argument is converted and is
        // then refused for that argument missing. The depth failure is what the property is refused
        // with, not the construction that followed from it.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxDepth(2))
            ->mapWithReport(['p' => ['child' => ['name' => 'leaf']]], DepthConstructorHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(DepthLimitExceededException::class, $errors[0]->getException());
    }

    /**
     * Builds a payload of nested "child" objects without any other key.
     *
     * @param int $levels Number of objects nested below the root
     *
     * @return array<string, mixed>
     */
    private static function bareChain(int $levels): array
    {
        $payload = [];

        for ($level = 0; $level < $levels; ++$level) {
            $payload = ['child' => $payload];
        }

        return $payload;
    }

    #[Test]
    public function itDoesNotBlameADepthFailureThatAMemberRecoveredFromOnALaterFailure(): void
    {
        // The inner union refuses the deep chain as an object and takes it as an array, so nothing
        // of the depth failure is left. The property next to it then fails for another reason, and
        // the outer union has to name that one.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxDepth(4))
            ->mapWithReport(['p' => ['value' => self::chain(6), 'bad' => []]], DepthStaleOuter::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertNotInstanceOf(DepthLimitExceededException::class, $errors[0]->getException());
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
    }

    #[Test]
    public function itKeepsTheDepthFailureWhenALaterMemberOnlyMismatches(): void
    {
        // The scalar member is tried after the object member hit the limit and fails on its own
        // account. The property is still refused with the limit.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxDepth(3))
            ->mapWithReport(['a' => self::chain(5)], DepthScalarUnionHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(DepthLimitExceededException::class, $errors[0]->getException());
    }

    #[Test]
    public function itKeepsTheDepthFailureWhenALaterMemberRecordsItsMismatch(): void
    {
        // The collection of integers does not descend, so it is tried after the object member hit the
        // limit, and it records the mismatch of its elements instead of throwing it. The property is
        // still refused with the limit.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxDepth(3))
            ->mapWithReport(['a' => self::chain(5)], DepthRecordingUnionHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(DepthLimitExceededException::class, $errors[0]->getException());
    }

    #[Test]
    public function itDoesNotBlameAnEarlierPropertysDepthFailureOnALaterMismatch(): void
    {
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxDepth(3))
            ->mapWithReport(['a' => self::chain(5), 'b' => 'zzz'], DepthScalarUnionHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(2, $errors);
        self::assertInstanceOf(DepthLimitExceededException::class, $errors[0]->getException());
        self::assertInstanceOf(TypeMismatchException::class, $errors[1]->getException());
    }

    #[Test]
    public function itNamesTheOwnPathOfEveryPropertyThatHitTheLimit(): void
    {
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxDepth(3))
            ->mapWithReport(['a' => self::chain(5), 'b' => self::chain(6)], DepthScalarUnionHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(2, $errors);
        self::assertSame('$.a.child.child.name', $errors[0]->getException()?->getPath());
        self::assertSame('$.b.child.child.name', $errors[1]->getException()?->getPath());
    }

    #[Test]
    public function itRefusesAChainOneLevelBeyondTheDefaultLimit(): void
    {
        $mapper = $this->getJsonMapper(config: JsonMapperConfiguration::lenient());
        $limit  = MappingContext::DEFAULT_MAX_DEPTH;

        // n nested objects reach n + 1 levels with the scalar at the end.
        self::assertSame(
            0,
            $mapper->mapWithReport(self::chain($limit - 1), DepthChainLink::class)->getReport()->getErrorCount(),
        );
        self::assertSame(
            1,
            $mapper->mapWithReport(self::chain($limit), DepthChainLink::class)->getReport()->getErrorCount(),
        );
    }

    #[Test]
    public function itNamesTheLevelThatFailedAndNotOneThatARecoveredUnionSaw(): void
    {
        // "bad" is refused for its depth, then "good" meets the limit as an object and is taken as
        // an array. The failure of the enclosing member is the one of "bad".
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxDepth(4))
            ->mapWithReport(['p' => ['bad' => self::chain(5), 'good' => self::chain(5)]], DepthRestoreHolder::class);

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors);

        $exception = $errors[0]->getException();

        self::assertInstanceOf(DepthLimitExceededException::class, $exception);
        self::assertStringStartsWith('$.p.bad.', $exception->getPath());
    }

    #[Test]
    public function itKeepsCountingWhenANestedUnionRecoversAndTheMemberMeetsTheLimitAgain(): void
    {
        // An earlier property leaves a depth failure behind, a nested union inside the member
        // recovers from the limit, and a later property of the member meets it again. The member is
        // refused with the limit, not as a type that was never wrong.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxDepth(4))
            ->mapWithReport(
                ['a' => self::chain(6), 'p' => ['value' => self::chain(6), 'deep' => self::chain(6)]],
                DepthProbeOuter::class,
            );

        $refusedPaths = [];

        foreach ($result->getReport()->getErrors() as $error) {
            $exception = $error->getException();

            self::assertNotInstanceOf(TypeMismatchException::class, $exception);

            if ($exception instanceof DepthLimitExceededException) {
                $refusedPaths[] = $exception->getPath();
            }
        }

        self::assertContains('$.p.deep.child.child.name', $refusedPaths);
    }

    #[Test]
    public function itDoesNotTryAFurtherObjectMemberAfterADepthFailure(): void
    {
        // Two members that nest in each other would be tried against every level, which doubles the
        // work per level. After a depth failure the second object member is left out, so the
        // number of objects built follows the depth.
        DepthMutualA::$built = 0;
        DepthMutualB::$built = 0;

        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxDepth(12))
            ->mapWithReport(self::bareChain(14), DepthMutualA::class);

        self::assertLessThan(self::LINEAR_WORK_BOUND, DepthMutualA::$built + DepthMutualB::$built);
        self::assertInstanceOf(
            DepthLimitExceededException::class,
            $result->getReport()->getErrors()[0]->getException(),
        );
    }

    #[Test]
    public function itDoesNotTryAFurtherCollectionMemberThatDescendsAfterADepthFailure(): void
    {
        // A valid payload deeper than the limit, through two collection members that nest in each
        // other. Each one meets the same limit, so trying both at every level doubles the work.
        DepthCollectionA::$built = 0;
        DepthCollectionB::$built = 0;

        $payload = ['child' => null];

        for ($level = 0; $level < 20; ++$level) {
            $payload = ['child' => [$payload]];
        }

        $result = $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxDepth(18))
            ->mapWithReport($payload, DepthCollectionA::class);

        self::assertLessThan(self::LINEAR_WORK_BOUND, DepthCollectionA::$built + DepthCollectionB::$built);
        self::assertSame(1, $result->getReport()->getErrorCount());
    }

    #[Test]
    public function itDoesNotTryAFurtherCollectionOfAUnionAfterADepthFailure(): void
    {
        // The values of both collection members are a union of the two classes, which descends
        // as an object does and has to be left out in the same way.
        DepthUnionCollectionA::$built = 0;
        DepthUnionCollectionB::$built = 0;

        $payload = ['child' => null];

        for ($level = 0; $level < 20; ++$level) {
            $payload = ['child' => [$payload]];
        }

        $this->getJsonMapper(config: JsonMapperConfiguration::lenient()->withMaxDepth(18))
            ->mapWithReport($payload, DepthUnionCollectionA::class);

        self::assertLessThan(self::LINEAR_WORK_BOUND, DepthUnionCollectionA::$built + DepthUnionCollectionB::$built);
    }

    #[Test]
    public function itDoesNotTryAFurtherObjectMemberAfterADepthFailureInStrictMode(): void
    {
        DepthMutualA::$built = 0;
        DepthMutualB::$built = 0;

        $mapper = $this->getJsonMapper(config: JsonMapperConfiguration::strict()->withMaxDepth(12));

        try {
            $mapper->map(self::bareChain(14), DepthMutualA::class);
            self::fail('The payload is deeper than the limit.');
        } catch (DepthLimitExceededException) {
            self::assertLessThan(self::LINEAR_WORK_BOUND, DepthMutualA::$built + DepthMutualB::$built);
        }
    }

    #[Test]
    public function itAcceptsALimitOfOne(): void
    {
        self::assertSame(1, JsonMapperConfiguration::lenient()->withMaxDepth(1)->getMaxDepth());
    }

    #[Test]
    public function itFallsBackToTheDefaultForABooleanAndAFloat(): void
    {
        self::assertSame(MappingContext::DEFAULT_MAX_DEPTH, JsonMapperConfiguration::fromArray(['maxDepth' => true])->getMaxDepth());
        self::assertSame(MappingContext::DEFAULT_MAX_DEPTH, JsonMapperConfiguration::fromArray(['maxDepth' => 5.0])->getMaxDepth());
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
