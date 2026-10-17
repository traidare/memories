<?php

declare(strict_types=1);

namespace OCA\Memories\Db;

use OCA\Memories\Exif;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;

/**
 * @psalm-type EmbeddedTagRow = array{path: string, tag: string, level: int, parent: ?string}
 */
trait TimelineWriteEmbeddedTags
{
    /**
     * Add the embedded tags of a file to a user's tag catalog.
     *
     * @param File    $file   File node to process
     * @param array   $exif   EXIF data extracted from the file
     * @param ?string $userId User to add the tags for (null = file owner)
     */
    public function processEmbeddedTags(File $file, array $exif, ?string $userId = null): void
    {
        if (null === $userId) {
            $owner = $file->getOwner();
            if (null === $owner) {
                return;
            }
            $userId = $owner->getUID();
        }

        $this->syncEmbeddedTags($userId, self::getEmbeddedTagRows($exif));
    }

    /**
     * Get the embedded tags of EXIF data as tag catalog rows, including their parents.
     *
     * @param array $exif EXIF data
     *
     * @return array<array-key, EmbeddedTagRow> Rows by path, e.g. "Travel" and "Travel/Beach"
     */
    public static function getEmbeddedTagRows(array $exif): array
    {
        $rows = [];
        foreach (Exif::extractEmbeddedTags($exif) as $parts) {
            $parent = null;
            $level = 0;
            foreach ($parts as $part) {
                $tag = trim((string) $part);
                if ('' === $tag) {
                    continue;
                }

                $path = null === $parent ? $tag : "{$parent}/{$tag}";
                $rows[$path] ??= ['path' => $path, 'tag' => $tag, 'level' => $level, 'parent' => $parent];
                $parent = $path;
                ++$level;
            }
        }

        return $rows;
    }

    /**
     * Add tags to a user's tag catalog, and optionally delete all others.
     *
     * @param string                           $userId User ID
     * @param array<array-key, EmbeddedTagRow> $rows   Tags from getEmbeddedTagRows
     * @param bool                             $prune  Delete the user's tags that are not in $rows
     */
    public function syncEmbeddedTags(string $userId, array $rows, bool $prune = false): void
    {
        if ([] === $rows && !$prune) {
            return;
        }

        // Get the IDs of the user's tags (only of the given ones unless pruning)
        $query = $this->connection->getQueryBuilder();
        $query->select('id', 'path')
            ->from('memories_embedded_tags')
            ->where($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
        ;
        if (!$prune) {
            $paths = array_column($rows, 'path');
            $query->andWhere($query->expr()->in('path', $query->createNamedParameter($paths, IQueryBuilder::PARAM_STR_ARRAY)));
        }

        /** @var array<array-key, int> $ids */
        $ids = [];
        $unused = [];
        foreach ($query->executeQuery()->fetchAll() as $row) {
            if (isset($rows[$row['path']])) {
                $ids[$row['path']] = (int) $row['id'];
            } else {
                $unused[] = (int) $row['id'];
            }
        }

        // Delete tags that are no longer used
        foreach (array_chunk($unused, 1000) as $chunk) {
            $query = $this->connection->getQueryBuilder();
            $query->delete('memories_embedded_tags')
                ->where($query->expr()->in('id', $query->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
                ->executeStatement()
            ;
        }

        // Add missing tags, parents first
        $missing = array_filter($rows, static fn (array $row): bool => !isset($ids[$row['path']]));
        usort($missing, static fn (array $a, array $b): int => $a['level'] <=> $b['level']);
        foreach ($missing as $row) {
            $parentId = null === $row['parent'] ? null : ($ids[$row['parent']] ?? null);
            if (null !== $row['parent'] && null === $parentId) {
                continue; // parent could not be added
            }

            if (null !== ($id = $this->addEmbeddedTag($userId, $row, $parentId))) {
                $ids[$row['path']] = $id;
            }
        }
    }

    /**
     * Delete tags from a user's tag catalog.
     *
     * @param string       $userId User ID
     * @param list<string> $paths  Tag paths
     */
    public function deleteEmbeddedTags(string $userId, array $paths): void
    {
        foreach (array_chunk($paths, 1000) as $chunk) {
            $query = $this->connection->getQueryBuilder();
            $query->delete('memories_embedded_tags')
                ->where($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
                ->andWhere($query->expr()->in('path', $query->createNamedParameter($chunk, IQueryBuilder::PARAM_STR_ARRAY)))
                ->executeStatement()
            ;
        }
    }

    /**
     * Add a tag to a user's tag catalog.
     *
     * @param EmbeddedTagRow $row
     *
     * @return ?int ID of the tag, or null if it is too long
     */
    private function addEmbeddedTag(string $userId, array $row, ?int $parentId): ?int
    {
        // Lengths of the tag and path columns
        if (mb_strlen($row['tag']) > 255 || mb_strlen($row['path']) > 1024) {
            return null;
        }

        try {
            $query = $this->connection->getQueryBuilder();
            $query->insert('memories_embedded_tags')
                ->values([
                    'user_id' => $query->createNamedParameter($userId),
                    'tag' => $query->createNamedParameter($row['tag']),
                    'parent_tag_id' => $query->createNamedParameter($parentId, null === $parentId ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT),
                    'path' => $query->createNamedParameter($row['path']),
                    'level' => $query->createNamedParameter($row['level'], IQueryBuilder::PARAM_INT),
                    'created_at' => $query->createNamedParameter(new \DateTime(), IQueryBuilder::PARAM_DATETIME_MUTABLE),
                ])
                ->executeStatement()
            ;

            return $query->getLastInsertId();
        } catch (\OCP\DB\Exception $e) {
            if (\OCP\DB\Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION !== $e->getReason()) {
                throw $e;
            }
        }

        // Added concurrently, e.g. by the indexer
        $query = $this->connection->getQueryBuilder();
        $id = $query->select('id')
            ->from('memories_embedded_tags')
            ->where($query->expr()->eq('user_id', $query->createNamedParameter($userId)))
            ->andWhere($query->expr()->eq('path', $query->createNamedParameter($row['path'])))
            ->executeQuery()
            ->fetchOne()
        ;

        return false === $id ? null : (int) $id;
    }
}
