<?php

declare(strict_types=1);

namespace OCA\Memories\Db;

use OCA\Memories\Exceptions;

final class EmbeddedTagFilter
{
    /** @return list<string> */
    public static function parse(mixed $value): array
    {
        if (null === $value || '' === $value) {
            return [];
        }
        if (!\is_string($value)) {
            throw Exceptions::BadRequest('embeddedTags must be a string');
        }

        return array_values(array_unique(array_filter(
            array_map(rawurldecode(...), explode(',', $value)),
            static fn (string $tag): bool => '' !== $tag,
        )));
    }

    /**
     * @param list<string> $selected
     * @param list<string> $photoTags
     */
    public static function matchesAll(array $selected, array $photoTags): bool
    {
        foreach ($selected as $tag) {
            $matches = false;
            foreach ($photoTags as $photoTag) {
                if ($photoTag === $tag || str_starts_with($photoTag, $tag.'/')) {
                    $matches = true;

                    break;
                }
            }
            if (!$matches) {
                return false;
            }
        }

        return true;
    }
}
