<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Resolution;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * All conditions of a selection that could not be applied.
 *
 * @implements IteratorAggregate<int, Diagnostic>
 */
final class Diagnostics implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<Diagnostic> */
    private array $items = [];

    public function add(Diagnostic $diagnostic): void
    {
        $this->items[] = $diagnostic;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return list<Diagnostic>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Filter keys of all diagnostics, without duplicates.
     *
     * @return list<string>
     */
    public function filterKeys(): array
    {
        return array_values(array_unique(array_map(fn (Diagnostic $d): string => $d->filterKey, $this->items)));
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        return array_map(fn (Diagnostic $d): array => $d->toArray(), $this->items);
    }
}
