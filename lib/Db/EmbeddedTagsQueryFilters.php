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

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

trait EmbeddedTagsQueryFilters
{
    protected IDBConnection $connection;

    /**
     * Transform query to filter by pattern using LIKE or REGEXP.
     *
     * @param string $pattern Pattern to search for
     */
    public function transformPatternFilter(IQueryBuilder &$query, string $pattern): void
    {
        // Sanitize pattern input
        $pattern = trim($pattern);
        if (empty($pattern)) {
            return;
        }

        // Try to determine if this is a regex pattern or simple search
        if ($this->isRegexPattern($pattern)) {
            // Use REGEXP for MySQL/MariaDB (both PLATFORM_MYSQL) or similar for other databases
            $provider = $this->connection->getDatabaseProvider();

            if (IDBConnection::PLATFORM_MYSQL === $provider) {
                $param = $query->createNamedParameter($pattern);
                $query->andWhere($query->expr()->orX(
                    $query->createFunction("et.tag REGEXP {$param}"),
                    $query->createFunction("et.path REGEXP {$param}"),
                ));
            } elseif (IDBConnection::PLATFORM_POSTGRES === $provider) {
                $param = $query->createNamedParameter($pattern);
                $query->andWhere($query->expr()->orX(
                    $query->createFunction("et.tag ~ {$param}"),
                    $query->createFunction("et.path ~ {$param}"),
                ));
            } else {
                // Fallback to LIKE for SQLite and others
                $this->transformLikeFilter($query, $pattern);
            }
        } else {
            // Use LIKE for simple text search
            $this->transformLikeFilter($query, $pattern);
        }
    }

    /**
     * Filter tag or path by a literal substring.
     */
    private function transformLikeFilter(IQueryBuilder &$query, string $pattern): void
    {
        $param = $query->createNamedParameter('%'.$this->escapeLikePattern($pattern).'%');
        $query->andWhere($query->expr()->orX(
            $query->createFunction("et.tag LIKE {$param} ESCAPE '!'"),
            $query->createFunction("et.path LIKE {$param} ESCAPE '!'"),
        ));
    }

    /**
     * Check if the pattern looks like a regex.
     */
    private function isRegexPattern(string $pattern): bool
    {
        // Simple heuristic: check for common regex characters
        return 1 === preg_match('/[.*+?^${}()|[\]\\\]/', $pattern);
    }

    /**
     * Escape special characters for LIKE pattern.
     */
    private function escapeLikePattern(string $pattern): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $pattern);
    }
}
