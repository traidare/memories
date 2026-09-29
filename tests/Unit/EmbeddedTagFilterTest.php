<?php

declare(strict_types=1);

namespace OCA\Memories\Tests\Unit;

use OCA\Memories\Db\EmbeddedTagFilter;
use OCA\Memories\HttpResponseException;
use OCA\Memories\Tests\TestCase;
use OCP\AppFramework\Http;

/**
 * @internal
 *
 * @covers \OCA\Memories\Db\EmbeddedTagFilter
 */
final class EmbeddedTagFilterTest extends TestCase
{
    public function testParseEncodedTagList(): void
    {
        self::assertSame(
            ['Travel/Beach', 'City, Night', '100%_!'],
            EmbeddedTagFilter::parse('Travel%2FBeach,City%2C%20Night,100%25_%21,Travel%2FBeach,'),
        );
        self::assertSame([], EmbeddedTagFilter::parse(null));
    }

    public function testParseRejectsNonString(): void
    {
        try {
            EmbeddedTagFilter::parse(['Travel']);
            self::fail('Expected HttpResponseException');
        } catch (HttpResponseException $e) {
            self::assertSame(Http::STATUS_BAD_REQUEST, $e->response->getStatus());
        }
    }

    public function testAllSelectedTagsAndPathBoundaries(): void
    {
        self::assertTrue(EmbeddedTagFilter::matchesAll(['Travel', 'Vacation'], ['Travel/Beach', 'Vacation']));
        self::assertTrue(EmbeddedTagFilter::matchesAll(['Travel/Beach'], ['Travel/Beach']));
        self::assertFalse(EmbeddedTagFilter::matchesAll(['Travel', 'Vacation'], ['Travel/Beach']));
        self::assertFalse(EmbeddedTagFilter::matchesAll(['Travel'], ['Travelogue']));
        self::assertFalse(EmbeddedTagFilter::matchesAll(['Travel/Beach'], ['Travel/Beachfront']));
    }
}
