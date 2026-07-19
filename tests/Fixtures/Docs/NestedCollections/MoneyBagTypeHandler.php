<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Docs\NestedCollections;

use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Value\TypeHandlerInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ObjectType;

use function is_array;
use function is_int;
use function is_object;

/**
 * Converts a payload into a {@see MoneyBag}.
 *
 * Registered explicitly by the caller, which is what has to outrank the collection strategy's
 * shape heuristic - MoneyBag is traversable and propertyless, so that heuristic claims it.
 *
 * @author  Rico Sonntag <mail@ricosonntag.de>
 * @license https://opensource.org/licenses/MIT
 * @link    https://github.com/magicsunday/jsonmapper/
 */
final class MoneyBagTypeHandler implements TypeHandlerInterface
{
    /**
     * Determines whether the handler converts the supplied type.
     *
     * @param Type  $type  Type metadata describing the target property.
     * @param mixed $value Raw value coming from the input payload.
     *
     * @return bool TRUE when the target type is a MoneyBag
     */
    public function supports(Type $type, mixed $value): bool
    {
        return ($type instanceof ObjectType) && ($type->getClassName() === MoneyBag::class);
    }

    /**
     * Converts the payload into a MoneyBag.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context provided by the mapper.
     *
     * @return MoneyBag Bag carrying the amount found in the payload
     */
    public function convert(Type $type, mixed $value, MappingContext $context): MoneyBag
    {
        if (is_object($value)) {
            $value = (array) $value;
        }

        $amount = is_array($value) ? ($value['amount'] ?? 0) : 0;

        return new MoneyBag(is_int($amount) ? $amount : 0);
    }
}
