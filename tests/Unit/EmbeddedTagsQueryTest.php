<?php

declare(strict_types=1);

namespace OCA\Memories\Tests\Unit;

use OCA\Memories\Db\EmbeddedTagsQuery;
use OCA\Memories\Tests\TestCase;

/**
 * @internal
 *
 * @covers \OCA\Memories\Db\EmbeddedTagsQuery
 */
final class EmbeddedTagsQueryTest extends TestCase
{
    public function testHierarchyKeepsDistinctRootsAndSiblings(): void
    {
        $query = (new \ReflectionClass(EmbeddedTagsQuery::class))->newInstanceWithoutConstructor();
        $rows = [
            ['id' => 1, 'parent_tag_id' => null, 'tag' => 'Travel', 'path' => 'Travel', 'level' => 0, 'created_at' => 'now'],
            ['id' => 2, 'parent_tag_id' => 1, 'tag' => 'Beach', 'path' => 'Travel/Beach', 'level' => 1, 'created_at' => 'now'],
            ['id' => 3, 'parent_tag_id' => 1, 'tag' => 'Mountain', 'path' => 'Travel/Mountain', 'level' => 1, 'created_at' => 'now'],
            ['id' => 4, 'parent_tag_id' => null, 'tag' => 'Vacation', 'path' => 'Vacation', 'level' => 0, 'created_at' => 'now'],
        ];

        $tree = (new \ReflectionMethod(EmbeddedTagsQuery::class, 'buildHierarchy'))->invoke($query, $rows);
        self::assertSame(['Travel', 'Vacation'], array_column($tree, 'tag'));
        self::assertSame(['Beach', 'Mountain'], array_column($tree[0]['children'], 'tag'));
        self::assertSame([], $tree[1]['children']);
        self::assertNotFalse(json_encode($tree));
    }
}
