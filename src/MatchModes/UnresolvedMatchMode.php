<?php

declare(strict_types=1);

namespace Ameax\FilterCore\MatchModes;

use Ameax\FilterCore\Contracts\MatchModeContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Placeholder for a match mode that was stored but is unknown now.
 *
 * Created by lenient parsing (FilterSelection::fromArray($data, lenient: true)), so a
 * selection can still be loaded, shown and saved back without losing the condition.
 * The Resolver reports it; it is never applied.
 */
final class UnresolvedMatchMode implements MatchModeContract
{
    public function __construct(private readonly string $originalKey) {}

    public function key(): string
    {
        return $this->originalKey;
    }

    public function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        throw new LogicException("Match mode '{$this->originalKey}' is unknown and cannot be applied. Resolve the selection first.");
    }

    public function applyToCollection(Collection $collection, string $column, mixed $value): Collection
    {
        throw new LogicException("Match mode '{$this->originalKey}' is unknown and cannot be applied. Resolve the selection first.");
    }
}
