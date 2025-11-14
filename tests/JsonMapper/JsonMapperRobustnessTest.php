<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\JsonMapper;

use MagicSunday\Test\Classes\Base;
use MagicSunday\Test\Classes\Collection;
use MagicSunday\Test\Classes\LargeDatasetItem;
use MagicSunday\Test\Classes\LargeDatasetRoot;
use MagicSunday\Test\Classes\RecursiveNode;
use MagicSunday\Test\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
final class JsonMapperRobustnessTest extends TestCase
{
    #[Test]
    public function itMapsEmptyCollectionsWithoutLosingTypeInformation(): void
    {
        $result = $this->getJsonMapper()->map(
            [
                'simpleArray'      => [],
                'simpleCollection' => [],
            ],
            Base::class,
        );

        self::assertInstanceOf(Base::class, $result);
        self::assertSame([], $result->simpleArray);
        self::assertInstanceOf(Collection::class, $result->simpleCollection);
        self::assertCount(0, $result->simpleCollection);
    }

    #[Test]
    public function itMapsDeeplyNestedRecursiveStructures(): void
    {
        $depth   = 8;
        $payload = ['name' => 'level-0'];
        $cursor  = &$payload;

        for ($i = 1; $i < $depth; ++$i) {
            $cursor['child'] = ['name' => 'level-' . $i];
            $cursor          = &$cursor['child'];
        }

        $result = $this->getJsonMapper()->map($payload, RecursiveNode::class);

        self::assertInstanceOf(RecursiveNode::class, $result);

        $node = $result;
        for ($i = 0; $i < $depth; ++$i) {
            self::assertSame('level-' . $i, $node->name);

            if ($i === $depth - 1) {
                self::assertNull($node->child);

                continue;
            }

            self::assertInstanceOf(RecursiveNode::class, $node->child);
            $node = $node->child;
        }
    }

    #[Test]
    public function itMapsLargeDatasetsWithinReasonableResources(): void
    {
        $items = [];
        for ($i = 0; $i < 500; ++$i) {
            $items[] = [
                'identifier' => $i,
                'label'      => 'Item #' . $i,
                'active'     => $i % 2 === 0,
            ];
        }

        $result = $this->getJsonMapper()->map(
            ['items' => $items],
            LargeDatasetRoot::class,
        );

        self::assertInstanceOf(LargeDatasetRoot::class, $result);

        /** @var LargeDatasetItem[] $datasetItems */
        $datasetItems = $result->items;

        self::assertCount(500, $datasetItems);
        self::assertContainsOnlyInstancesOf(LargeDatasetItem::class, $datasetItems);
        self::assertSame('Item #0', $datasetItems[0]->label);
        self::assertTrue($datasetItems[0]->active);
        self::assertSame(499, $datasetItems[499]->identifier);
        self::assertFalse($datasetItems[499]->active);
    }
}
