<?php

declare(strict_types=1);

namespace OCA\Memories\Db;

/**
 * @psalm-type QueryTransform = \Closure(\OCP\DB\QueryBuilder\IQueryBuilder, bool): void
 */
final class TimelineQuery
{
    use TimelineQueryBase;
    use TimelineQueryDays;
    use TimelineQueryFilters;
    use TimelineQueryFolders;
    use TimelineQueryLivePhoto;
    use TimelineQueryMap;
    use TimelineQueryNativeX;
    use TimelineQuerySingleItem;

    protected bool $filterExifBySQL = true;

    public function setFilterExifBySQL(bool $value): void
    {
        $this->filterExifBySQL = $value;
    }

    public function shouldFilterExifBySQL(): bool
    {
        return $this->filterExifBySQL && 'mysql' === $this->connection->getDatabaseProvider();
    }
}
