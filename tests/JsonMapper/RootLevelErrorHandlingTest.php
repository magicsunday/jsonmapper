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
use MagicSunday\JsonMapper\Exception\MappingException;
use MagicSunday\JsonMapper\Exception\MissingConstructorArgumentException;
use MagicSunday\JsonMapper\Exception\MissingPropertyException;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use MagicSunday\JsonMapper\Report\MappingResult;
use MagicSunday\Test\Classes\RequiredConstructorArgumentDto;
use MagicSunday\Test\Classes\RequiredConstructorArgumentDtoHolder;
use MagicSunday\Test\Classes\TwoRequiredPropertiesDto;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

use function array_map;
use function preg_quote;

/**
 * A mapping failure on the ROOT object escaped mapWithReport() instead of being recorded, while
 * the identical failure one level down was collected by the property loop. Same error, different
 * contract depending on nesting depth - so a caller could not rely on the report at all.
 *
 * @internal
 */
final class RootLevelErrorHandlingTest extends TestCase
{
    #[Test]
    public function itRecordsAMissingConstructorArgumentOnTheRootObject(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            [],
            RequiredConstructorArgumentDto::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'One unbuildable root object produces exactly one record.');
        self::assertInstanceOf(MissingConstructorArgumentException::class, $errors[0]->getException());
    }

    #[Test]
    public function itTreatsTheRootTheSameWayAsANestedObject(): void
    {
        // The point of the fix: the two lanes must agree. Both payloads fail for the same reason,
        // one at the root and one a level down.
        $root = $this->getJsonMapper()->mapWithReport(
            [],
            RequiredConstructorArgumentDto::class,
        );

        $nested = $this->getJsonMapper()->mapWithReport(
            ['dto' => []],
            RequiredConstructorArgumentDtoHolder::class,
        );

        // Both lanes are anchored to a literal rather than to each other: a mutation that shifted
        // both together - two records per failure, or the nested lane regressing to the root's
        // old escape-then-record - would satisfy a bare count-equals-count comparison.
        self::assertSame(1, $root->getReport()->getErrorCount(), 'Root: one unbuildable object, one record.');
        self::assertSame(1, $nested->getReport()->getErrorCount(), 'Nested: same failure, same record count.');
        self::assertInstanceOf(
            MissingConstructorArgumentException::class,
            $root->getReport()->getErrors()[0]->getException(),
        );
        self::assertInstanceOf(
            MissingConstructorArgumentException::class,
            $nested->getReport()->getErrors()[0]->getException(),
        );
    }

    #[Test]
    public function itTreatsTheRootTheSameWayAsANestedObjectInStrictMode(): void
    {
        // The parity thesis has to hold in strict mode too, and that lane changed: strict failures
        // are now collected rather than thrown. A regression leaving only the NESTED lane aborting
        // would restore exactly the root-versus-nested asymmetry this file exists to prevent, and
        // the lenient arm above would not notice.
        $root = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            [],
            RequiredConstructorArgumentDto::class,
        );

        $nested = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            ['dto' => []],
            RequiredConstructorArgumentDtoHolder::class,
        );

        // Pinned on the exception KIND, not on how many records each lane produced. The counts
        // genuinely differ - the root yields two (the missing property, then the unbuildable
        // object), the nested lane one, because strict mode never validates a nested object's
        // properties at all. That asymmetry predates this change and is tracked in issue #105:
        // asserting equality would bless the defect, and asserting today's numbers would break
        // this test the moment #105 is fixed for the right reason. The kind survives either way.
        self::assertContains(
            MissingConstructorArgumentException::class,
            $this->exceptionClasses($root),
            'Root: the unbuildable object is reported as such.',
        );
        self::assertContains(
            MissingConstructorArgumentException::class,
            $this->exceptionClasses($nested),
            'Nested: the same failure kind, one level down.',
        );
        // The mapped values differ for a documented reason, not an inconsistent one: the root IS
        // the object that could not be constructed, so there is nothing to hand back, whereas the
        // holder itself constructs fine and merely keeps its rejected property unassigned.
        self::assertNull($root->getValue(), 'Root: nothing could be built.');
        self::assertInstanceOf(
            RequiredConstructorArgumentDtoHolder::class,
            $nested->getValue(),
            'Nested: the holder survives, only the rejected property is left unassigned.',
        );
    }

    #[Test]
    public function itRecordsAScalarPayloadOnTheRootObject(): void
    {
        // The counterpart from the scalar-payload guard: it used to escape here as well.
        $result = $this->getJsonMapper()->mapWithReport(
            'oops',
            RequiredConstructorArgumentDto::class,
        );

        $errors = $result->getReport()->getErrors();

        self::assertCount(1, $errors, 'One rejected payload produces exactly one record.');
        self::assertInstanceOf(TypeMismatchException::class, $errors[0]->getException());
    }

    #[Test]
    public function itReturnsNullAsTheMappedValueWhenTheRootCannotBeBuilt(): void
    {
        // There is no partially built root object to hand back, so the caller has to be able to
        // tell "nothing was produced" apart from "an object was produced" - by the value, not by
        // an exception it never sees.
        $result = $this->getJsonMapper()->mapWithReport(
            [],
            RequiredConstructorArgumentDto::class,
        );

        self::assertNull($result->getValue());
        self::assertTrue($result->getReport()->hasErrors());
    }

    #[Test]
    public function itStillThrowsInStrictModeOnMap(): void
    {
        // map() is the lane that raises. The concrete type is pinned deliberately: which exception
        // a strict-mode caller catches is part of the contract, and here it is the missing
        // PROPERTY rather than the missing constructor argument, because strict mode validates the
        // payload before construction is attempted. Should that order ever change, this fails
        // loudly and gets decided on purpose instead of drifting.
        $this->expectException(MissingPropertyException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote('$.name', '/') . '/');

        $this->getJsonMapper(config: JsonMapperConfiguration::strict())->map(
            [],
            RequiredConstructorArgumentDto::class,
        );
    }

    #[Test]
    public function itCollectsInStrictModeOnMapWithReport(): void
    {
        // The counterpart, and the contract decided in issue #65: mapWithReport() exists to hand
        // back a report, so it collects even under strict(). Strict mode still decides WHAT counts
        // as a failure - the record below is a MissingPropertyException, an error lenient mode
        // would never have produced - it just no longer decides whether the run aborts.
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            [],
            RequiredConstructorArgumentDto::class,
        );

        $errors = $result->getReport()->getErrors();

        // The count is PROVISIONAL, not a contract: the second record duplicates the first, since
        // construction fails for exactly the value strict validation already reported missing.
        // AGENTS.md says a rejected value is recorded once, so this is a defect - tracked as issue
        // #106 - and the assertion is here to make its resolution visible rather than to bless it.
        // What the change under test really claims is the part below: recorded, not thrown.
        self::assertCount(2, $errors, 'Provisional, pending issue #106: the second record duplicates the first.');
        self::assertInstanceOf(MissingPropertyException::class, $errors[0]->getException());
        self::assertInstanceOf(MissingConstructorArgumentException::class, $errors[1]->getException());
        self::assertSame('$.name', $errors[0]->getPath(), 'The record names the offending property.');
        self::assertNull($result->getValue());
    }

    #[Test]
    public function itCollectsEveryStrictModeFailureRatherThanOnlyTheFirst(): void
    {
        // The defect issue #65 actually reports: aborting on the first failure made the report a
        // single-entry list at best. Two independent violations must both appear, which is what
        // distinguishes collect-all from "throws, caught one level up".
        $result = $this->getJsonMapper(config: JsonMapperConfiguration::strict())->mapWithReport(
            [],
            TwoRequiredPropertiesDto::class,
        );

        $paths = array_map(
            static fn (MappingError $error): string => $error->getPath(),
            $result->getReport()->getErrors(),
        );

        self::assertSame(['$.first', '$.second'], $paths, 'Both missing properties are reported.');
    }

    #[Test]
    public function itLeavesASuccessfulMappingUntouched(): void
    {
        $result = $this->getJsonMapper()->mapWithReport(
            ['name' => 'accepted'],
            RequiredConstructorArgumentDto::class,
        );

        $dto = $result->getValue();

        self::assertInstanceOf(RequiredConstructorArgumentDto::class, $dto);
        self::assertSame('accepted', $dto->name);
        self::assertFalse($result->getReport()->hasErrors());
    }

    /**
     * Returns the class name of every recorded exception, for asserting on the KIND of failure
     * without pinning how many records it happened to produce.
     *
     * @param MappingResult $result Result whose report is inspected
     *
     * @return list<string> Class names of the recorded exceptions, in record order
     */
    private function exceptionClasses(MappingResult $result): array
    {
        $classes = [];

        foreach ($result->getReport()->getErrors() as $error) {
            $exception = $error->getException();

            // A record may carry a message without an exception. Skipped rather than represented
            // by a placeholder, so that assertContains() cannot match one by accident.
            if ($exception instanceof MappingException) {
                $classes[] = $exception::class;
            }
        }

        return $classes;
    }
}
