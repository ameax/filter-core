<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Selections;

use Ameax\FilterCore\Data\FilterValue;
use Ameax\FilterCore\Enums\GroupOperatorEnum;
use Ameax\FilterCore\Enums\RelationModeEnum;
use Ameax\FilterCore\Filters\Filter;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Represents a group of filter conditions with an operator (AND/OR).
 *
 * FilterGroups can contain:
 * - FilterValue items (leaf conditions)
 * - Nested FilterGroup items (for complex logic)
 *
 * A group can be bound to a relation of the model: its conditions then apply to the
 * same related record, as one whereHas() — "has a contact whose name contains X and
 * whose position is Y" — instead of to any related record each.
 * - has: some related record matches all conditions of the group
 * - doesnt_have: no related record matches them (records without any count too)
 * - has_none: there is no related record at all; the group has no items
 *
 * @implements Arrayable<string, mixed>
 */
final class FilterGroup implements Arrayable, JsonSerializable
{
    /** @var array<FilterValue|FilterGroup> */
    protected array $items = [];

    protected ?string $relation = null;

    protected RelationModeEnum $relationMode = RelationModeEnum::HAS;

    /**
     * Relation data of a lenient read that could not be resolved, kept as given so the
     * group survives loading and saving and the Resolver can report it.
     *
     * @var array{relation: mixed, relation_mode: mixed}|null
     */
    protected ?array $unresolvedRelation = null;

    public function __construct(
        protected GroupOperatorEnum $operator = GroupOperatorEnum::AND
    ) {}

    /**
     * A group whose conditions must hold for one and the same related record.
     */
    public static function has(string $relation, GroupOperatorEnum $operator = GroupOperatorEnum::AND): self
    {
        return (new self($operator))->forRelation($relation, RelationModeEnum::HAS);
    }

    /**
     * A group that no related record may match (records without any related record match).
     */
    public static function doesntHave(string $relation, GroupOperatorEnum $operator = GroupOperatorEnum::AND): self
    {
        return (new self($operator))->forRelation($relation, RelationModeEnum::DOESNT_HAVE);
    }

    /**
     * Records without any related record. The group takes no items.
     */
    public static function hasNone(string $relation): self
    {
        return (new self)->forRelation($relation, RelationModeEnum::HAS_NONE);
    }

    /**
     * An empty group with the same operator and relation, e.g. to rebuild it.
     */
    public function emptyCopy(): self
    {
        $copy = new self($this->operator);
        $copy->relation = $this->relation;
        $copy->relationMode = $this->relationMode;
        $copy->unresolvedRelation = $this->unresolvedRelation;

        return $copy;
    }

    /**
     * The relation this group is bound to, or null for a plain group.
     */
    public function getRelation(): ?string
    {
        return $this->relation;
    }

    public function getRelationMode(): RelationModeEnum
    {
        return $this->relationMode;
    }

    public function isRelationGroup(): bool
    {
        return $this->relation !== null || $this->unresolvedRelation !== null;
    }

    /**
     * The relation data a lenient read could not resolve, as given, or null.
     *
     * @return array{relation: mixed, relation_mode: mixed}|null
     */
    public function getUnresolvedRelation(): ?array
    {
        return $this->unresolvedRelation;
    }

    /**
     * Why the relation data could not be resolved, or null for a resolved group.
     */
    public function unresolvedRelationReason(): ?string
    {
        if ($this->unresolvedRelation === null) {
            return null;
        }
        ['relation' => $relation, 'relation_mode' => $mode] = $this->unresolvedRelation;

        if (! is_string($relation) || $relation === '') {
            return 'The relation of a group must be a non-empty string, got '.self::describe($relation);
        }
        if (! is_string($mode) || RelationModeEnum::tryFrom($mode) === null) {
            return 'Relation mode '.self::describe($mode).' is unknown';
        }

        // Relation and mode are valid: the stored group is "has none" with conditions.
        return "A 'has none' group for relation '{$relation}' takes no conditions";
    }

    private static function describe(mixed $value): string
    {
        return is_string($value) ? "'{$value}'" : (json_encode($value) ?: get_debug_type($value));
    }

    /**
     * @throws \InvalidArgumentException when the data cannot be read, unless lenient
     */
    private function readRelation(mixed $relation, mixed $mode, bool $hasItems, bool $lenient): void
    {
        $resolvedMode = match (true) {
            $mode === null => RelationModeEnum::HAS,
            is_string($mode) => RelationModeEnum::tryFrom($mode),
            default => null,
        };
        // "Has none" takes no conditions; stored data that has some cannot be read either.
        if (is_string($relation) && $relation !== '' && $resolvedMode !== null
            && ! ($resolvedMode === RelationModeEnum::HAS_NONE && $hasItems)) {
            $this->forRelation($relation, $resolvedMode);

            return;
        }

        $this->unresolvedRelation = ['relation' => $relation, 'relation_mode' => $mode];
        if (! $lenient) {
            $reason = $this->unresolvedRelationReason();
            $this->unresolvedRelation = null;

            throw new \InvalidArgumentException((string) $reason);
        }
    }

    private function forRelation(string $relation, RelationModeEnum $mode): self
    {
        if ($relation === '') {
            throw new \InvalidArgumentException('The relation of a group must not be empty');
        }
        $this->relation = $relation;
        $this->relationMode = $mode;

        return $this;
    }

    /**
     * Create an AND group.
     */
    public static function and(): self
    {
        return new self(GroupOperatorEnum::AND);
    }

    /**
     * Create an OR group.
     */
    public static function or(): self
    {
        return new self(GroupOperatorEnum::OR);
    }

    /**
     * Get the group operator.
     */
    public function getOperator(): GroupOperatorEnum
    {
        return $this->operator;
    }

    /**
     * Get all items in this group.
     *
     * @return array<FilterValue|FilterGroup>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Check if the group is empty.
     */
    public function isEmpty(): bool
    {
        // "Has no related record" is a condition of its own, without items.
        if ($this->relation !== null && $this->relationMode === RelationModeEnum::HAS_NONE) {
            return false;
        }
        // Unreadable relation data is reported by the Resolver, not dropped silently.
        if ($this->unresolvedRelation !== null) {
            return false;
        }

        return $this->items === [];
    }

    /**
     * Get the number of items in this group.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Add a FilterValue to this group.
     */
    public function add(FilterValue $filterValue): self
    {
        $this->guardHasNone();
        $this->items[] = $filterValue;

        return $this;
    }

    /**
     * Add a nested FilterGroup to this group.
     */
    public function addGroup(FilterGroup $group): self
    {
        $this->guardHasNone();
        $this->items[] = $group;

        return $this;
    }

    /**
     * Start building a filter value for a filter class (AND context).
     *
     * @param  class-string<Filter>  $filterClass
     */
    public function where(string $filterClass): FilterGroupBuilder
    {
        return new FilterGroupBuilder($filterClass, $this);
    }

    /**
     * Add an OR group with a callback.
     *
     * @example
     * $group->orWhere(function($orGroup) {
     *     $orGroup->where(StatusFilter::class)->is('pending');
     *     $orGroup->where(StatusFilter::class)->is('draft');
     * });
     */
    public function orWhere(callable $callback): self
    {
        $orGroup = self::or();
        $callback($orGroup);

        if (! $orGroup->isEmpty()) {
            $this->items[] = $orGroup;
        }

        return $this;
    }

    /**
     * Add an AND group with a callback.
     *
     * @example
     * $group->andWhere(function($andGroup) {
     *     $andGroup->where(StatusFilter::class)->is('active');
     *     $andGroup->where(CountFilter::class)->greaterThan(5);
     * });
     */
    public function andWhere(callable $callback): self
    {
        $andGroup = self::and();
        $callback($andGroup);

        if (! $andGroup->isEmpty()) {
            $this->items[] = $andGroup;
        }

        return $this;
    }

    /**
     * Add a nested group bound to a relation: its conditions hold for the same related record.
     */
    public function whereHas(string $relation, callable $callback, GroupOperatorEnum $operator = GroupOperatorEnum::AND): self
    {
        $group = self::has($relation, $operator);
        $callback($group);

        if (! $group->isEmpty()) {
            $this->addGroup($group);
        }

        return $this;
    }

    /**
     * Add a nested group that no related record may match.
     */
    public function whereDoesntHave(string $relation, callable $callback, GroupOperatorEnum $operator = GroupOperatorEnum::AND): self
    {
        $group = self::doesntHave($relation, $operator);
        $callback($group);

        if (! $group->isEmpty()) {
            $this->addGroup($group);
        }

        return $this;
    }

    /**
     * Only records without any related record.
     */
    public function whereHasNone(string $relation): self
    {
        return $this->addGroup(self::hasNone($relation));
    }

    /**
     * Get all FilterValues from this group (flattened, ignores nesting).
     *
     * @return array<FilterValue>
     */
    public function getAllFilterValues(): array
    {
        $values = [];

        foreach ($this->items as $item) {
            if ($item instanceof FilterValue) {
                $values[] = $item;
            } elseif ($item instanceof FilterGroup) {
                $values = array_merge($values, $item->getAllFilterValues());
            }
        }

        return $values;
    }

    /**
     * Get all unique filter keys from this group.
     *
     * @return array<string>
     */
    public function getAllFilterKeys(): array
    {
        return array_unique(array_map(
            fn (FilterValue $fv) => $fv->getFilterKey(),
            $this->getAllFilterValues()
        ));
    }

    /**
     * Check if this group contains nested groups.
     */
    public function hasNestedGroups(): bool
    {
        foreach ($this->items as $item) {
            if ($item instanceof FilterGroup) {
                return true;
            }
        }

        return false;
    }

    private function guardHasNone(): void
    {
        if ($this->relation !== null && $this->relationMode === RelationModeEnum::HAS_NONE) {
            throw new \LogicException("A 'has none' group for relation '{$this->relation}' takes no conditions");
        }
    }

    /**
     * Convert to array.
     *
     * @return array{operator: string, relation?: mixed, relation_mode?: mixed, items: array<array<string, mixed>>}
     */
    public function toArray(): array
    {
        $data = ['operator' => $this->operator->value];
        if ($this->unresolvedRelation !== null) {
            $data['relation'] = $this->unresolvedRelation['relation'];
            $data['relation_mode'] = $this->unresolvedRelation['relation_mode'];
        } elseif ($this->relation !== null) {
            $data['relation'] = $this->relation;
            $data['relation_mode'] = $this->relationMode->value;
        }
        $data['items'] = array_map(
            fn (FilterValue|FilterGroup $item) => $item->toArray(),
            $this->items
        );

        return $data;
    }

    /**
     * @return array{operator: string, relation?: mixed, relation_mode?: mixed, items: array<array<string, mixed>>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Create a FilterGroup from an array.
     *
     * @param  array{operator?: string, relation?: mixed, relation_mode?: mixed, items?: array<array<string, mixed>>}  $data
     * @param  bool  $lenient  see FilterValue::fromArray(); also keeps relation data that
     *                         cannot be read (empty relation, unknown mode, a "has none"
     *                         group with conditions) for the Resolver to report
     *                         (UnresolvedRelation) instead of throwing
     */
    public static function fromArray(array $data, bool $lenient = false): self
    {
        $operator = isset($data['operator'])
            ? GroupOperatorEnum::from($data['operator'])
            : GroupOperatorEnum::AND;

        $group = new self($operator);
        if (isset($data['relation'])) {
            $group->readRelation($data['relation'], $data['relation_mode'] ?? null, ($data['items'] ?? []) !== [], $lenient);
        }

        foreach ($data['items'] ?? [] as $itemData) {
            // Check if this is a nested group (has 'operator' key) or a filter value
            if (isset($itemData['operator'])) {
                $group->addGroup(self::fromArray($itemData, $lenient));
            } else {
                $group->add(FilterValue::fromArray($itemData, $lenient));
            }
        }

        return $group;
    }
}
