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
use MagicSunday\JsonMapper\Context\MappingContext;
use MagicSunday\Test\Classes\Base;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The options bag is an extension point - a type handler may keep its own keys there. Rebuilding a
 * configuration from the context for each nested object wrote the bag back wholesale, and
 * toOptions() only knows the mapper's own keys, so every custom key vanished from the first nested
 * object onward without a word.
 *
 * @internal
 */
final class CustomContextOptionTest extends TestCase
{
    #[Test]
    public function itKeepsACustomOptionAcrossNestedObjects(): void
    {
        $options           = JsonMapperConfiguration::lenient()->toOptions();
        $options['tenant'] = 'acme';

        $context = new MappingContext([], $options);

        $this->getJsonMapper()->map(
            $this->getJsonAsObject('{"simple": {"name": "n"}}'),
            Base::class,
            null,
            $context,
        );

        self::assertSame(
            'acme',
            $context->getOption('tenant'),
            'A key the mapper does not know about is not its to discard.',
        );
    }

    #[Test]
    public function itLeavesTheCallersOwnConfigurationIntact(): void
    {
        // The other half: a context handed in by the caller must come back describing the same
        // configuration it went in with, not one rebuilt from a nested mapping step.
        $context = new MappingContext([], JsonMapperConfiguration::strict()->toOptions());

        try {
            $this->getJsonMapper()->map(
                $this->getJsonAsObject('{"simple": {"name": "n"}}'),
                Base::class,
                null,
                $context,
            );
        } catch (\MagicSunday\JsonMapper\Exception\MappingException) {
            // Strict mode may reject this fixture; what matters is the context afterwards.
        }

        self::assertTrue($context->isStrictMode(), 'The caller asked for strict mode and still has it.');
    }
}
