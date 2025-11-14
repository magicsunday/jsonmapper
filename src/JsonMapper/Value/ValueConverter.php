<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Value;

use LogicException;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Value\Strategy\ValueConversionStrategyInterface;
use Symfony\Component\TypeInfo\Type;

use function sprintf;

/**
 * Converts JSON values according to the registered strategies.
 */
final class ValueConverter
{
    /**
     * @var list<ValueConversionStrategyInterface>
     */
    private array $strategies = [];

    /**
     * Registers the strategy at the end of the chain.
     *
     * @param ValueConversionStrategyInterface $strategy Strategy executed when it supports the provided value.
     *
     * @return void
     */
    public function addStrategy(ValueConversionStrategyInterface $strategy): void
    {
        $this->strategies[] = $strategy;
    }

    /**
     * Converts the value using the first matching strategy.
     *
     * @param mixed $value Raw JSON value that needs to be converted.
     * @param Type $type Target type metadata that should be satisfied by the conversion result.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return mixed Result from the first strategy that declares support for the value.
     */
    public function convert(mixed $value, Type $type, MappingContext $context): mixed
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($value, $type, $context)) {
                return $strategy->convert($value, $type, $context);
            }
        }

        throw new LogicException(
            sprintf('No conversion strategy available for type %s.', $type::class)
        );
    }
}
