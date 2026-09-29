<?php

declare(strict_types=1);

namespace OCA\Memories\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\QueryBuilder\IQueryFunction;
use OCP\ITags;

trait TimelineQueryFilters
{
    use TimelineQueryBase;

    public function transformMinRatingFilter(IQueryBuilder &$query, bool $aggregate, int $minRating): void
    {
        if ($minRating <= 0 || !$this->shouldFilterExifBySQL()) {
            return;
        }

        $query->andWhere('JSON_EXTRACT(m.exif, \'$.Rating\') >= :minRating');
        $query->setParameter('minRating', $minRating, IQueryBuilder::PARAM_INT);
    }

    /**
     * @param list<string> $embeddedTags
     */
    public function transformEmbeddedTagsFilter(IQueryBuilder &$query, bool $aggregate, array $embeddedTags): void
    {
        if (empty($embeddedTags) || !$this->shouldFilterExifBySQL()) {
            return;
        }

        $fields = ['Keywords', 'Subject', 'TagsList', 'HierarchicalSubject'];

        foreach ($embeddedTags as $index => $tag) {
            $or = $query->expr()->orX();

            foreach ($fields as $field) {
                $separators = \in_array($field, ['Keywords', 'HierarchicalSubject'], true) ? ['/', '|'] : ['/'];
                foreach ($separators as $separator) {
                    $rawTag = '|' === $separator ? str_replace('/', '|', $tag) : $tag;
                    $suffix = '/' === $separator ? 'slash' : 'pipe';
                    $tagParam = "tag_{$index}_{$field}_{$suffix}";
                    $prefixParam = "prefix_{$index}_{$field}_{$suffix}";
                    $json = "JSON_EXTRACT(m.exif, '$.{$field}')";

                    $or->add("JSON_CONTAINS({$json}, JSON_QUOTE(:{$tagParam})) = 1");
                    $or->add("JSON_SEARCH({$json}, 'one', :{$prefixParam}, '!') IS NOT NULL");
                    $query->setParameter($tagParam, $rawTag, IQueryBuilder::PARAM_STR);
                    $query->setParameter(
                        $prefixParam,
                        str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $rawTag).$separator.'%',
                        IQueryBuilder::PARAM_STR,
                    );
                }
            }

            $query->andWhere($or);
        }
    }

    public function transformFavoriteFilter(IQueryBuilder &$query, bool $aggregate): void
    {
        if ($this->util->isLoggedIn()) {
            $query->innerJoin('m', 'vcategory_to_object', 'vcoi', $query->expr()->andX(
                $query->expr()->eq('vcoi.objid', 'm.fileid'),
                $query->expr()->in('vcoi.categoryid', $this->getFavoriteVCategoryFun($query)),
            ));
        }
    }

    public function addFavoriteTag(IQueryBuilder &$query): void
    {
        if ($this->util->isLoggedIn()) {
            $query->leftJoin('m', 'vcategory_to_object', 'vco', $query->expr()->andX(
                $query->expr()->eq('vco.objid', 'm.fileid'),
                $query->expr()->in('vco.categoryid', $this->getFavoriteVCategoryFun($query)),
            ));
            $query->addSelect('vco.categoryid');
        }
    }

    public function transformVideoFilter(IQueryBuilder &$query, bool $aggregate): void
    {
        $query->andWhere($query->expr()->eq('m.isvideo', $query->expr()->literal(1)));
    }

    public function transformLimit(IQueryBuilder &$query, bool $aggregate, int $limit): void
    {
        if ($limit >= 1) {
            $query->setMaxResults(min($limit, 100));
        }
    }

    private function getFavoriteVCategoryFun(IQueryBuilder &$query): IQueryFunction
    {
        $sub = $query->getConnection()->getQueryBuilder();
        $sub->select('id')
            ->from('vcategory', 'vc')
            ->where($sub->expr()->andX(
                $sub->expr()->eq('type', $sub->expr()->literal('files')),
                $sub->expr()->eq('uid', $query->createNamedParameter($this->util->getUID())),
                $sub->expr()->eq('category', $sub->expr()->literal(ITags::TAG_FAVORITE)),
            ))
        ;

        return SQL::subquery($query, $sub);
    }
}
