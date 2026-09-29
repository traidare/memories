<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Varun Patil <radialapps@gmail.com>
 * @author Varun Patil <radialapps@gmail.com>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Memories\Db;

use OCA\Memories\Util;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class EmbeddedTagsQuery
{
    use EmbeddedTagsQueryFilters;

    public const TAGS_SELECT = [
        'id', 'user_id', 'tag', 'parent_tag_id',
        'path', 'level', 'created_at',
    ];

    public function __construct(
        protected IDBConnection $connection,
        protected Util $util,
    ) {}

    public function getBuilder(): IQueryBuilder
    {
        return $this->connection->getQueryBuilder();
    }

    /**
     * Get all tags for a user in flat manner with optional filtering and pagination.
     *
     * @param null|string $pattern Optional regex pattern to filter tags
     * @param null|int    $limit   Optional limit for pagination
     * @param null|int    $offset  Optional offset for pagination
     *
     * @return array List of tags
     */
    public function getTagsFlat(?string $pattern = null, ?int $limit = null, ?int $offset = null): array
    {
        $query = $this->getBuilder();

        $query->select(self::TAGS_SELECT)
            ->from('memories_embedded_tags', 'et')
            ->where($query->expr()->eq('user_id', $query->createNamedParameter($this->util->getUID())))
            ->orderBy('path', 'ASC')
        ;

        // Apply pattern filter if provided
        if (null !== $pattern) {
            $this->transformPatternFilter($query, $pattern);
        }

        // Apply pagination if provided
        if (null !== $limit) {
            $query->setMaxResults($limit);
        }
        if (null !== $offset) {
            $query->setFirstResult($offset);
        }

        return $query->executeQuery()->fetchAll() ?: [];
    }

    /**
     * Get all tags for a user in hierarchical structure.
     *
     * @param null|string $pattern Optional regex pattern to filter tags
     *
     * @return array Hierarchical structure of tags
     */
    public function getTagsHierarchical(?string $pattern = null): array
    {
        // Get all tags first
        $query = $this->getBuilder();

        $query->select(self::TAGS_SELECT)
            ->from('memories_embedded_tags', 'et')
            ->where($query->expr()->eq('user_id', $query->createNamedParameter($this->util->getUID())))
            ->orderBy('level', 'ASC')
            ->addOrderBy('tag', 'ASC')
        ;

        $allTags = $query->executeQuery()->fetchAll() ?: [];

        if (null !== $pattern && '' !== trim($pattern)) {
            $byId = array_column($allTags, null, 'id');
            $keep = [];
            foreach ($this->getTagsFlat($pattern) as $match) {
                $id = $match['id'];
                while (isset($byId[$id]) && !isset($keep[$id])) {
                    $keep[$id] = true;
                    $id = $byId[$id]['parent_tag_id'];
                }
            }
            $allTags = array_values(array_filter($allTags, static fn (array $tag): bool => isset($keep[$tag['id']])));
        }

        // Build hierarchical structure
        return $this->buildHierarchy($allTags);
    }

    /**
     * Get count of tags matching pattern.
     *
     * @param null|string $pattern Optional regex pattern to filter tags
     *
     * @return int Number of tags
     */
    public function getTagsCount(?string $pattern = null): int
    {
        $query = $this->getBuilder();

        $query->select($query->func()->count('*', 'count'))
            ->from('memories_embedded_tags', 'et')
            ->where($query->expr()->eq('user_id', $query->createNamedParameter($this->util->getUID())))
        ;

        // Apply pattern filter if provided
        if (null !== $pattern) {
            $this->transformPatternFilter($query, $pattern);
        }

        $result = $query->executeQuery()->fetch();

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Build hierarchical structure from flat tags array.
     *
     * @param array $tags Flat array of tags
     *
     * @return array Hierarchical structure
     */
    private function buildHierarchy(array $tags): array
    {
        // Group the tags by parent, with tags whose parent is missing at the top
        $ids = array_column($tags, 'id', 'id');
        $children = [];
        foreach ($tags as $tag) {
            $parentId = $tag['parent_tag_id'];
            $children[null !== $parentId && isset($ids[$parentId]) ? (string) $parentId : ''][] = $tag;
        }

        return $this->buildChildren($children, '');
    }

    /**
     * Build the tree of the children of a tag.
     *
     * @param array<array-key, list<array>> $children Tags grouped by parent ID
     * @param string                        $parentId Parent ID, or '' for the top level
     *
     * @return list<array> Hierarchical structure
     */
    private function buildChildren(array $children, string $parentId): array
    {
        return array_map(fn (array $tag): array => [
            'id' => $tag['id'],
            'tag' => $tag['tag'],
            'path' => $tag['path'],
            'level' => $tag['level'],
            'created_at' => $tag['created_at'],
            'children' => $this->buildChildren($children, (string) $tag['id']),
        ], $children[$parentId] ?? []);
    }
}
