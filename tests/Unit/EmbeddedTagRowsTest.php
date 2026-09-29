<?php

declare(strict_types=1);

namespace OCA\Memories\Tests\Unit;

use OCA\Memories\Db\TimelineWrite;
use OCA\Memories\Exif;
use OCA\Memories\Tests\TestCase;

/**
 * @internal
 *
 * @covers \OCA\Memories\Db\TimelineWrite
 * @covers \OCA\Memories\Exif
 */
final class EmbeddedTagRowsTest extends TestCase
{
    public function testExtractNumericTags(): void
    {
        // exiftool returns numeric tags as numbers
        self::assertSame(
            ['2024', '1.5', 'Travel/Beach'],
            Exif::extractEmbeddedTags([
                'Keywords' => [2024, 'Travel/Beach'],
                'Subject' => 1.5,
            ], true),
        );
    }

    public function testRowsIncludeParents(): void
    {
        // Flat tags come first
        self::assertSame([
            'Vacation' => ['path' => 'Vacation', 'tag' => 'Vacation', 'level' => 0, 'parent' => null],
            'Travel' => ['path' => 'Travel', 'tag' => 'Travel', 'level' => 0, 'parent' => null],
            'Travel/Beach' => ['path' => 'Travel/Beach', 'tag' => 'Beach', 'level' => 1, 'parent' => 'Travel'],
            'Travel/Beach/Sunset' => ['path' => 'Travel/Beach/Sunset', 'tag' => 'Sunset', 'level' => 2, 'parent' => 'Travel/Beach'],
        ], TimelineWrite::getEmbeddedTagRows([
            'HierarchicalSubject' => ['Travel|Beach', ' Travel | Beach | Sunset '],
            'Keywords' => ['Vacation'],
        ]));
    }

    public function testRowsOfNumericTags(): void
    {
        // Keys of numeric paths are integers, so the path is part of the row
        $rows = TimelineWrite::getEmbeddedTagRows(['Keywords' => [2024, '2024/12']]);
        self::assertSame(['2024', '2024/12'], array_column($rows, 'path'));
        self::assertSame('2024', $rows['2024/12']['parent']);
        self::assertSame([], TimelineWrite::getEmbeddedTagRows([]));
    }
}
