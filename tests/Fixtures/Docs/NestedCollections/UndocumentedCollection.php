<?php

/**
 * This file is part of the package magicsunday/jsonmapper.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MagicSunday\Test\Fixtures\Docs\NestedCollections;

// A class with NO docblock at all, which is a different case from UnannotatedCollection: that one
// has a docblock the resolver reads and finds no element tag in, while this one makes
// getDocComment() answer false so there is nothing to read.
//
// Deliberately commented with line comments rather than a docblock - the absence IS the fixture,
// and a docblock here would silently turn it into a duplicate of UnannotatedCollection.
//
// It extends nothing: the resolver asks the class for its docblock and its element tags, and
// neither question involves the base class, so a container base would only add a generic-types
// obligation that a docblock is the way to satisfy.
final class UndocumentedCollection
{
}
