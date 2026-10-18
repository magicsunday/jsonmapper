<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\NullPayload;

use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Value\TypeHandlerInterface;
use MagicSunday\Test\Fixtures\Enum\SampleStatus;
use Symfony\Component\TypeInfo\Type;

use function is_string;

/**
 * A handler that claims every value and counts how often it was asked.
 *
 * Claiming everything is the point. A handler that only matches one class cannot show whether a
 * null was kept away from it or simply did not match, whereas this one is asked about every value
 * that reaches the strategy chain, so a count of zero proves that nothing did.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class RecordingTypeHandler implements TypeHandlerInterface
{
    /**
     * Number of times the mapper asked whether this handler supports a value.
     */
    private int $askedCount = 0;

    /**
     * Counts the question and claims the value.
     *
     * @param Type  $type  Type metadata describing the target property.
     * @param mixed $value Raw value coming from the input payload.
     *
     * @return bool Always TRUE.
     */
    public function supports(Type $type, mixed $value): bool
    {
        ++$this->askedCount;

        return true;
    }

    /**
     * Resolves a known status case from a string payload.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return SampleStatus|null Matching case, or NULL when the payload names none.
     */
    public function convert(Type $type, mixed $value, MappingContext $context): ?SampleStatus
    {
        return is_string($value) ? SampleStatus::tryFrom($value) : null;
    }

    /**
     * Returns how often the mapper asked this handler about a value.
     *
     * @return int Number of support questions so far.
     */
    public function getAskedCount(): int
    {
        return $this->askedCount;
    }
}
