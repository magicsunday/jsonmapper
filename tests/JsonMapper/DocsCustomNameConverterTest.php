<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\JsonMapper;
use MagicSunday\Test\Fixtures\Converter\UpperSnakeCaseConverter;
use MagicSunday\Test\Fixtures\Docs\NameConverter\Event;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

final class DocsCustomNameConverterTest extends TestCase
{
    #[Test]
    public function itMapsUsingTheCustomNameConverterRecipe(): void
    {
        $converter = new UpperSnakeCaseConverter();
        $extractor = new PropertyInfoExtractor([new ReflectionExtractor()], [new PhpDocExtractor()]);
        $mapper    = new JsonMapper(
            $extractor,
            PropertyAccess::createPropertyAccessor(),
            $converter,
        );

        $json = $this->getJsonAsObject('{"EVENT_CODE":"signup"}');

        $event = $mapper->map($json, Event::class);

        self::assertInstanceOf(Event::class, $event);
        self::assertSame('signup', $event->eventcode);
    }
}
