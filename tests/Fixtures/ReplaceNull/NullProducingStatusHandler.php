<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\ReplaceNull;

use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Value\TypeHandlerInterface;
use MagicSunday\Test\Fixtures\Enum\SampleStatus;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ObjectType;

use function is_string;

/**
 * A handler that answers "no value" for a payload it does not recognise.
 *
 * A conversion result of null is not the same thing as a null payload, and only a handler can
 * produce one - the built-in strategies report a value they cannot convert instead. It is how a
 * consumer says "treat anything unfamiliar as absent" without losing the rest of the object.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class NullProducingStatusHandler implements TypeHandlerInterface
{
    /**
     * Claims the sample status type.
     *
     * @param Type  $type  Type metadata describing the target property.
     * @param mixed $value Raw value coming from the input payload.
     *
     * @return bool TRUE for the sample status type.
     */
    public function supports(Type $type, mixed $value): bool
    {
        return ($type instanceof ObjectType) && ($type->getClassName() === SampleStatus::class);
    }

    /**
     * Resolves a known case, and answers null for everything else.
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
}
