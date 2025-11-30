<?php

declare(strict_types=1);

namespace Ameax\FilterCore\DateRange;

use Carbon\Carbon;

/**
 * Resolved date range with concrete start and end dates.
 *
 * Start or end can be null for open-ended ranges:
 * - null start: everything up to end ("older than")
 * - null end: everything from start onwards ("since")
 */
readonly class ResolvedDateRange
{
    public function __construct(
        public ?Carbon $start,
        public ?Carbon $end,
    ) {}

    /**
     * Create from two Carbon instances.
     */
    public static function make(?Carbon $start, ?Carbon $end): self
    {
        return new self($start, $end);
    }

    /**
     * Create from date strings.
     */
    public static function fromStrings(?string $start, ?string $end): self
    {
        return new self(
            $start !== null ? Carbon::parse($start) : null,
            $end !== null ? Carbon::parse($end) : null,
        );
    }

    /**
     * Check if this is a closed range (both boundaries defined).
     */
    public function isClosed(): bool
    {
        return $this->start !== null && $this->end !== null;
    }

    /**
     * Check if this is an open-start range (no lower boundary).
     */
    public function isOpenStart(): bool
    {
        return $this->start === null && $this->end !== null;
    }

    /**
     * Check if this is an open-end range (no upper boundary).
     */
    public function isOpenEnd(): bool
    {
        return $this->start !== null && $this->end === null;
    }

    /**
     * Check if this range is completely unbounded.
     */
    public function isUnbounded(): bool
    {
        return $this->start === null && $this->end === null;
    }

    /**
     * Check if a date falls within this range.
     */
    public function contains(Carbon $date): bool
    {
        if ($this->start !== null && $date->lt($this->start)) {
            return false;
        }

        if ($this->end !== null && $date->gt($this->end)) {
            return false;
        }

        return true;
    }

    /**
     * Get the duration of this range in days (null if unbounded).
     */
    public function durationInDays(): ?int
    {
        if ($this->start === null || $this->end === null) {
            return null;
        }

        return (int) $this->start->diffInDays($this->end);
    }

    /**
     * Convert to array format for serialization.
     *
     * @return array{start: string|null, end: string|null}
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start?->toDateTimeString(),
            'end' => $this->end?->toDateTimeString(),
        ];
    }

    /**
     * Convert to ISO 8601 date strings.
     *
     * @return array{start: string|null, end: string|null}
     */
    public function toIso8601(): array
    {
        return [
            'start' => $this->start?->toIso8601String(),
            'end' => $this->end?->toIso8601String(),
        ];
    }

    /**
     * Convert to date-only strings (Y-m-d).
     *
     * @return array{start: string|null, end: string|null}
     */
    public function toDateStrings(): array
    {
        return [
            'start' => $this->start?->toDateString(),
            'end' => $this->end?->toDateString(),
        ];
    }
}
