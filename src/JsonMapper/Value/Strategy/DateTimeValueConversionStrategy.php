<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\JsonMapper\Value\Strategy;

use DateInterval;
use DateTimeInterface;
use DateTimeZone;
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\JsonMapper\Exception\TypeMismatchException;
use ReflectionClass;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Throwable;

use function get_debug_type;
use function is_a;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/**
 * Converts ISO-8601 strings and timestamps into date/time value objects.
 */
final class DateTimeValueConversionStrategy implements ValueConversionStrategyInterface
{
    use ObjectTypeConversionGuardTrait;

    /**
     * Determines whether the requested type is a supported date or interval class.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return bool TRUE when the type represents a supported date/time object.
     */
    public function supports(Type $type, mixed $value, MappingContext $context): bool
    {
        $objectType = $this->extractObjectType($type);

        if (!$objectType instanceof ObjectType) {
            return false;
        }

        $className = $objectType->getClassName();

        if (is_a($className, DateInterval::class, true)) {
            return true;
        }

        if (!is_a($className, DateTimeInterface::class, true)) {
            return false;
        }

        // Any DateTimeInterface implementation, mutable included - convert() builds whatever class
        // the property asks for. It has to be instantiable, though: an interface extending
        // DateTimeInterface, or an abstract subclass, would reach `new $className` and raise a
        // native Error that no MappingException catch can collect, turning a reportable mapping
        // failure into a fatal. Claiming neither leaves them to the object strategy, which refuses
        // them as a recorded mismatch. Picking an implementation for a property typed by the
        // interface would also be the mapper deciding mutability on the caller's behalf.
        //
        // No class_exists() guard: the is_a() above already answers false for a symbol that does
        // not exist, and ReflectionClass handles an interface perfectly well - it simply reports
        // it as not instantiable, which is the answer wanted here.
        return (new ReflectionClass($className))->isInstantiable();
    }

    /**
     * Converts ISO-8601 strings and timestamps into the desired date/time object.
     *
     * The concrete class comes from the property, so a mutable DateTime stays mutable and an
     * immutable one stays immutable - the caller's choice is not second-guessed.
     *
     * @param Type           $type    Type metadata describing the target property.
     * @param mixed          $value   Raw value coming from the input payload.
     * @param MappingContext $context Mapping context providing configuration such as strict mode.
     *
     * @return mixed Instance of the configured date/time class.
     */
    public function convert(Type $type, mixed $value, MappingContext $context): mixed
    {
        return $this->convertObjectValue(
            $type,
            $context,
            $value,
            static function (string $className, mixed $value) use ($context) {
                // Resolved once per value and guarded: DateTimeZone raises
                // DateInvalidTimeZoneException for an identifier it does not know, and that is no
                // MappingException - it would escape a run that promised a report. The
                // configuration validates on the way in, but the option bag is an extension point
                // that can be populated directly, so the strategy cannot assume it was.
                try {
                    $timezone = new DateTimeZone($context->getDefaultTimezone());
                } catch (Throwable) {
                    throw new TypeMismatchException($context->getPath(), $className, 'invalid timezone');
                }

                // A float is accepted as a timestamp: a JSON number with a fraction is an
                // ordinary way to express sub-second precision, and it used to be refused
                // outright, leaving the property uninitialised so that reading it back raised an
                // Error rather than reporting a failure.
                //
                // No is_finite() guard. INF and NAN format as literals no date constructor
                // understands, so they raise there and the catch below reports them - as the same
                // TypeMismatchException, at the same path, naming the same type. A guard would be
                // a branch nothing could observe.
                if (!is_string($value) && !is_int($value) && !is_float($value)) {
                    throw new TypeMismatchException($context->getPath(), $className, get_debug_type($value));
                }

                if (is_a($className, DateInterval::class, true)) {
                    if (!is_string($value)) {
                        throw new TypeMismatchException($context->getPath(), $className, get_debug_type($value));
                    }

                    try {
                        return new $className($value);
                    } catch (Throwable) {
                        throw new TypeMismatchException($context->getPath(), $className, get_debug_type($value));
                    }
                }

                if (is_string($value)) {
                    // The timezone is passed unconditionally. PHP applies it only when the FORMAT
                    // carries none of its own, so a payload that states its offset keeps it, while
                    // a zoneless format stops falling back to the host default - which made the
                    // same JSON decode to a different instant on every differently configured
                    // deployment, silently.
                    $parsed = $className::createFromFormat(
                        $context->getDefaultDateFormat(),
                        $value,
                        $timezone,
                    );

                    if ($parsed instanceof DateTimeInterface) {
                        return $parsed;
                    }
                }

                // Six decimals because that is the precision DateTime keeps. A leading @ makes the
                // value an absolute instant, so it is UTC by definition and no host can shift it.
                $formatted = match (true) {
                    is_int($value)   => '@' . $value,
                    is_float($value) => '@' . sprintf('%.6F', $value),
                    default          => $value,
                };

                try {
                    // The timezone reaches this constructor too. It is the route a string takes
                    // when it does not match the configured format - which, under the default
                    // ATOM, is every zoneless string - so leaving it out would have kept the host
                    // dependency on the more common of the two paths while fixing the rarer one.
                    // As with createFromFormat(), a value stating its own offset keeps it.
                    return new $className($formatted, $timezone);
                } catch (Throwable) {
                    // Throwable rather than Exception: a subclass whose constructor demands
                    // something else raises a TypeError, and an unparsable value can reach a
                    // constructor that rejects it outright. Both are native Errors, which no
                    // MappingException catch upstream collects - they would leave the caller with
                    // a fatal where a report entry was promised.
                    throw new TypeMismatchException($context->getPath(), $className, get_debug_type($value));
                }
            }
        );
    }
}
