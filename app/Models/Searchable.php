<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @mixin Model */
trait Searchable
{
    /** @return non-empty-list<string> Column names, or relation.column paths. */
    abstract protected function searchableColumns(): array;

    public static function normalizeSearchKeyword(mixed $keyword): string
    {
        $keyword = is_string($keyword) ? trim($keyword) : '';

        return mb_strlen($keyword) >= 2 ? $keyword : '';
    }

    /** @param Builder<static> $query */
    #[Scope]
    protected function search(Builder $query, mixed $keyword): void
    {
        $keyword = static::normalizeSearchKeyword($keyword);

        if ($keyword === '') {
            return;
        }

        $term = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($keyword)) . '%';

        $query->where(function (Builder $query) use ($term): void {
            foreach ($this->searchableColumns() as $column) {
                $separator = strrpos($column, '.');

                if ($separator !== false) {
                    $relation = substr($column, 0, $separator);
                    $column = substr($column, $separator + 1);
                    $query->orWhereHas($relation, function (Builder $related) use ($column, $term): void {
                        $column = $related->getQuery()->getGrammar()->wrap($related->qualifyColumn($column));
                        $related->whereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$term]);
                    });
                } else {
                    $column = $query->getQuery()->getGrammar()->wrap($query->qualifyColumn($column));
                    $query->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$term]);
                }
            }
        });
    }
}
