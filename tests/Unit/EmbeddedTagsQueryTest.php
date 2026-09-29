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

    public function testHierarchyNestsGrandchildrenAndKeepsOrphans(): void
    {
        $query = (new \ReflectionClass(EmbeddedTagsQuery::class))->newInstanceWithoutConstructor();
        $rows = [
            ['id' => 1, 'parent_tag_id' => null, 'tag' => 'Travel', 'path' => 'Travel', 'level' => 0, 'created_at' => 'now'],
            ['id' => 2, 'parent_tag_id' => 1, 'tag' => 'Beach', 'path' => 'Travel/Beach', 'level' => 1, 'created_at' => 'now'],
            ['id' => 3, 'parent_tag_id' => 2, 'tag' => 'Sunset', 'path' => 'Travel/Beach/Sunset', 'level' => 2, 'created_at' => 'now'],
            ['id' => 4, 'parent_tag_id' => 99, 'tag' => 'Night', 'path' => 'City/Night', 'level' => 1, 'created_at' => 'now'],
        ];

        $tree = (new \ReflectionMethod(EmbeddedTagsQuery::class, 'buildHierarchy'))->invoke($query, $rows);
        self::assertSame(['Travel', 'Night'], array_column($tree, 'tag'));
        self::assertSame(['id', 'tag', 'path', 'level', 'created_at', 'children'], array_keys($tree[0]));
        self::assertSame(['Sunset'], array_column($tree[0]['children'][0]['children'], 'tag'));
        self::assertSame([], $tree[0]['children'][0]['children'][0]['children']);
        self::assertSame([], $tree[1]['children']);
    }
}
