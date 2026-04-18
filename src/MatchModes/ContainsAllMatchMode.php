<?php

declare(strict_types=1);

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Tokenised contains: every whitespace-separated token must be contained in the column.
 *
 * Example: value "Marienplatz München" on column `locality` generates
 *   (locality LIKE '%Marienplatz%' AND locality LIKE '%München%')
 *
 * Empty input produces no constraint (matches all rows).
 */
class ContainsAllMatchMode implements MatchModeContract
{
    public function key(): string
    {
        return 'containsAll';
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $tokens = $this->tokenize($value);

        if (empty($tokens)) {
            return;
        }

        $query->where(function ($query) use ($column, $tokens): void {
            foreach ($tokens as $token) {
                $query->where($column, 'like', '%'.$token.'%');
            }
        });
    }

    public function applyToCollection(Collection $collection, string $column, mixed $value): Collection
    {
        $tokens = $this->tokenize($value);

        if (empty($tokens)) {
            return $collection;
        }

        return $collection->filter(function ($item) use ($column, $tokens): bool {
            $itemValue = data_get($item, $column);

            if ($itemValue === null) {
                return false;
            }

            $haystack = (string) $itemValue;

            foreach ($tokens as $token) {
                if (! str_contains($haystack, $token)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Split the value on whitespace and drop empty tokens.
     *
     * @return array<int, string>
     */
    private function tokenize(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $tokens = preg_split('/\s+/u', trim((string) $value)) ?: [];

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }
}
